const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { createAdminCustomersRouter } = require('../routes/admin-customers');

function makeDb() {
    const transaction = { begun: 0, committed: 0, rolledBack: 0, queries: [] };
    const customers = new Map([
        [1, { id: 1, nama: 'Dewi', no_wa: '08123456789', alamat: 'Jalan Mawar', created_at: new Date('2026-01-01') }],
        [2, { id: 2, nama: 'Rizal', no_wa: '08987654321', alamat: null, created_at: new Date('2026-01-02') }],
        [3, { id: 3, nama: 'Pelanggan dihapus', no_wa: 'DIHAPUS-3', alamat: null, created_at: new Date('2026-01-03') }]
    ]);
    const services = new Map([
        [10, { id: 10, customer_id: 1 }],
        [11, { id: 11, customer_id: 1 }],
        [20, { id: 20, customer_id: 2 }],
        [30, { id: 30, customer_id: 3 }]
    ]);
    const serviceDetails = new Map([[31, { service_id: 10 }], [32, { service_id: 11 }], [33, { service_id: 20 }]]);
    const serviceLogs = new Map([[41, { service_id: 10 }], [42, { service_id: 11 }], [43, { service_id: 20 }]]);
    const accessTokens = new Map([
        [100, { id: 100, service_id: 10, revoked_at: null }],
        [101, { id: 101, service_id: 11, revoked_at: null }],
        [200, { id: 200, service_id: 20, revoked_at: null }]
    ]);
    const sessions = new Map([
        ['session-hash-1', { access_token_id: 100 }],
        ['session-hash-2', { access_token_id: 101 }],
        ['session-hash-other', { access_token_id: 200 }]
    ]);

    async function execute(sql) {
        if (sql.includes('FROM customers c') && sql.includes('COUNT(s.id)')) {
            const visibleCustomers = sql.includes("c.nama <> 'Pelanggan dihapus'")
                ? [...customers.values()].filter(customer => customer.nama !== 'Pelanggan dihapus' && !customer.no_wa.startsWith('DIHAPUS-'))
                : [...customers.values()];
            return [visibleCustomers.map(customer => ({
                ...customer,
                jumlah_servis: [...services.values()].filter(service => service.customer_id === customer.id).length
            }))];
        }
        throw new Error(`Unexpected DB query: ${sql}`);
    }

    return {
        customers, services, serviceDetails, serviceLogs, accessTokens, sessions, execute, transaction,
        async getConnection() {
            return {
                beginTransaction: async () => { transaction.begun++; },
                commit: async () => { transaction.committed++; },
                rollback: async () => { transaction.rolledBack++; },
                release: () => {},
                async execute(sql, params = []) {
                    transaction.queries.push(sql.replace(/\s+/g, ' ').trim());
                    const id = Number(params[0]);
                    if (sql.includes('SELECT id, nama, no_wa FROM customers WHERE id = ? FOR UPDATE')) {
                        const customer = customers.get(id);
                        return [customer ? [{ id: customer.id, nama: customer.nama, no_wa: customer.no_wa }] : []];
                    }
                    if (sql.includes('SELECT id FROM customers WHERE id = ? FOR UPDATE')) {
                        const customer = customers.get(id);
                        return [customer ? [{ id: customer.id }] : []];
                    }
                    if (sql.includes('SELECT id FROM services WHERE customer_id = ? FOR UPDATE')) {
                        return [[...services.values()]
                            .filter(service => service.customer_id === id)
                            .map(service => ({ id: service.id }))];
                    }
                    if (sql.includes('UPDATE customers SET nama = ?, no_wa = ?, alamat = ? WHERE id = ?')) {
                        const customer = customers.get(Number(params[3]));
                        if (customer) [customer.nama, customer.no_wa, customer.alamat] = params.slice(0, 3);
                        return [{ affectedRows: customer ? 1 : 0 }];
                    }
                    if (sql.includes('DELETE cs FROM customer_sessions')) {
                        for (const [sessionHash, session] of sessions) {
                            const token = accessTokens.get(session.access_token_id);
                            if (token && params.includes(token.service_id)) sessions.delete(sessionHash);
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('DELETE FROM service_details WHERE service_id IN')) {
                        for (const [detailId, detail] of serviceDetails) {
                            if (params.includes(detail.service_id)) serviceDetails.delete(detailId);
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('DELETE FROM service_logs WHERE service_id IN')) {
                        for (const [logId, log] of serviceLogs) {
                            if (params.includes(log.service_id)) serviceLogs.delete(logId);
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('DELETE FROM service_access_tokens WHERE service_id IN')) {
                        for (const [tokenId, token] of accessTokens) {
                            if (params.includes(token.service_id)) accessTokens.delete(tokenId);
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('DELETE FROM services WHERE id IN')) {
                        for (const serviceId of params) services.delete(Number(serviceId));
                        return [{ affectedRows: params.length }];
                    }
                    if (sql.includes('DELETE FROM customers WHERE id = ?')) {
                        const customer = customers.get(id);
                        if (customer) customers.delete(id);
                        return [{ affectedRows: customer ? 1 : 0 }];
                    }
                    throw new Error(`Unexpected transaction query: ${sql}`);
                }
            };
        }
    };
}

async function withServer(run) {
    const db = makeDb();
    const app = express();
    app.use(express.json());
    app.use('/api/admin/customers', createAdminCustomersRouter(db));
    const server = app.listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    const base = `http://127.0.0.1:${server.address().port}/api/admin/customers`;
    try { await run({ base, db }); }
    finally { await new Promise(resolve => server.close(resolve)); }
}

test('admin customer list includes each customer service count', async () => {
    await withServer(async ({ base }) => {
        const response = await fetch(base);
        assert.equal(response.status, 200);
        const result = await response.json();
        assert.equal(result.status, 'success');
        assert.equal(result.data.length, 2);
        assert.equal(result.data.some(customer => customer.nama === 'Pelanggan dihapus'), false);
        assert.equal(result.data.find(customer => customer.id === 1).jumlah_servis, 2);
        assert.equal(result.data.find(customer => customer.id === 2).jumlah_servis, 1);
    });
});

test('admin can update customer identity while retaining service history', async () => {
    await withServer(async ({ base, db }) => {
        const response = await fetch(`${base}/1`, {
            method: 'PUT',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ nama: 'Dewi Mulyani', no_wa: '081111222233', alamat: 'Jalan Melati' })
        });
        assert.equal(response.status, 200);
        assert.deepEqual(
            { nama: db.customers.get(1).nama, no_wa: db.customers.get(1).no_wa, alamat: db.customers.get(1).alamat },
            { nama: 'Dewi Mulyani', no_wa: '081111222233', alamat: 'Jalan Melati' }
        );
        assert.equal([...db.services.values()].filter(service => service.customer_id === 1).length, 2);
        assert.equal(db.accessTokens.get(100).revoked_at, null);
    });
});

test('deleting customer permanently removes its services and dependent records in a transaction', async () => {
    await withServer(async ({ base, db }) => {
        const response = await fetch(`${base}/1`, { method: 'DELETE' });
        assert.equal(response.status, 200);
        assert.deepEqual(await response.json(), { success: true, message: 'Data pelanggan berhasil dihapus' });
        assert.equal(db.customers.has(1), false);
        assert.equal([...db.services.values()].some(service => service.customer_id === 1), false);
        assert.equal([...db.serviceDetails.values()].some(detail => [10, 11].includes(detail.service_id)), false);
        assert.equal([...db.serviceLogs.values()].some(log => [10, 11].includes(log.service_id)), false);
        assert.equal([...db.accessTokens.values()].some(token => [10, 11].includes(token.service_id)), false);
        assert.equal(db.sessions.size, 1);
        assert.equal(db.sessions.has('session-hash-other'), true);
        assert.deepEqual(
            [db.transaction.begun, db.transaction.committed, db.transaction.rolledBack],
            [1, 1, 0]
        );
        const deletes = db.transaction.queries.filter(query => query.startsWith('DELETE'));
        assert.match(deletes[0], /^DELETE cs FROM customer_sessions/);
        assert.match(deletes[1], /^DELETE FROM service_details/);
        assert.match(deletes[2], /^DELETE FROM service_logs/);
        assert.match(deletes[3], /^DELETE FROM service_access_tokens/);
        assert.match(deletes[4], /^DELETE FROM services/);
        assert.match(deletes[5], /^DELETE FROM customers/);
    });
});

test('customer deletion rejects invalid IDs and rolls back when the customer is missing', async () => {
    await withServer(async ({ base, db }) => {
        assert.equal((await fetch(`${base}/abc`, { method: 'DELETE' })).status, 400);
        assert.equal((await fetch(`${base}/999`, { method: 'DELETE' })).status, 404);
        assert.deepEqual(
            [db.transaction.begun, db.transaction.committed, db.transaction.rolledBack],
            [1, 0, 1]
        );
    });
});
