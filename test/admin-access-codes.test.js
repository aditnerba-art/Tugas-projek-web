const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { hashCustomerToken } = require('../lib/customer-token');
const { createAdminCustomersRouter } = require('../routes/admin-customers');

function makeDb() {
    const services = new Map([
        [10, { id: 10, no_nota: 'SRV-10', customer_id: 1 }],
        [20, { id: 20, no_nota: 'SRV-20', customer_id: 2 }]
    ]);
    const tokens = new Map([
        [100, { id: 100, service_id: 10, token_hash: hashCustomerToken('12345'), expires_at: new Date(Date.now() + 86400000), revoked_at: null }],
        [200, { id: 200, service_id: 20, token_hash: hashCustomerToken('67890'), expires_at: new Date(Date.now() + 86400000), revoked_at: null }]
    ]);
    const sessions = new Map([
        ['old-session', { access_token_id: 100 }],
        ['other-session', { access_token_id: 200 }]
    ]);
    const accessCodeRows = [
        { service_id: 10, no_nota: 'SRV-10', nama_pelanggan: 'Dewi', jenis_unit: 'Laptop', merek_tipe: 'Lenovo', status: 'masuk', has_active_code: 1 },
        { service_id: 30, no_nota: 'SRV-30', nama_pelanggan: 'Pelanggan dihapus', jenis_unit: 'Laptop', merek_tipe: 'Acer', status: 'masuk', has_active_code: 0 }
    ];

    return {
        services, tokens, sessions, accessCodeRows,
        async execute(sql) {
            if (sql.includes('FROM services s') && sql.includes('EXISTS')) {
                const rows = sql.includes("c.nama <> 'Pelanggan dihapus'")
                    ? accessCodeRows.filter(row => row.nama_pelanggan !== 'Pelanggan dihapus')
                    : accessCodeRows;
                return [rows];
            }
            throw new Error(`Unexpected query: ${sql}`);
        },
        async getConnection() {
            return {
                beginTransaction: async () => {},
                commit: async () => {},
                rollback: async () => {},
                release: () => {},
                async execute(sql, params = []) {
                    const serviceId = Number(params[0]);
                    if (sql.includes('SELECT id FROM services WHERE id = ? FOR UPDATE')) {
                        const service = services.get(serviceId);
                        return [service ? [{ id: service.id }] : []];
                    }
                    if (sql.includes('DELETE cs FROM customer_sessions')) {
                        for (const [hash, session] of sessions) {
                            const token = tokens.get(session.access_token_id);
                            if (token?.service_id === serviceId) sessions.delete(hash);
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('UPDATE service_access_tokens') && sql.includes('revoked_at = CURRENT_TIMESTAMP')) {
                        for (const token of tokens.values()) {
                            if (token.service_id === serviceId && !token.revoked_at) token.revoked_at = new Date();
                        }
                        return [{ affectedRows: 1 }];
                    }
                    if (sql.includes('INSERT INTO service_access_tokens')) {
                        const [id, tokenHash, expiresAt] = [Math.max(0, ...tokens.keys()) + 1, params[1], params[2]];
                        tokens.set(id, { id, service_id: serviceId, token_hash: tokenHash, expires_at: new Date(expiresAt), revoked_at: null });
                        return [{ insertId: id }];
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

test('access-code rotation rejects invalid and missing service IDs', async () => {
    await withServer(async ({ base }) => {
        assert.equal((await fetch(`${base}/access-codes/nope`, { method: 'POST' })).status, 400);
        assert.equal((await fetch(`${base}/access-codes/999`, { method: 'POST' })).status, 404);
    });
});

test('admin access-code list exposes service metadata but never the stored hash or code', async () => {
    await withServer(async ({ base }) => {
        const response = await fetch(`${base}/access-codes`);
        assert.equal(response.status, 200);
        const result = await response.json();
        assert.equal(result.status, 'success');
        assert.equal(result.data.length, 1);
        assert.equal(result.data.some(service => service.nama_pelanggan === 'Pelanggan dihapus'), false);
        assert.equal(result.data[0].service_id, 10);
        assert.equal(result.data[0].has_active_code, 1);
        assert.equal(Object.hasOwn(result.data[0], 'token_hash'), false);
        assert.equal(Object.hasOwn(result.data[0], 'customer_token'), false);
    });
});

test('admin can generate a replacement access code and see it once', async () => {
    await withServer(async ({ base, db }) => {
        const response = await fetch(`${base}/access-codes/10`, { method: 'POST' });
        assert.equal(response.status, 200);
        const result = await response.json();
        assert.equal(result.status, 'success');
        assert.match(result.data.customer_token, /^\d{5}$/);
        assert.equal(db.tokens.get(100).revoked_at instanceof Date, true);
        assert.equal(db.sessions.size, 1);
        assert.equal(db.sessions.has('other-session'), true);
        assert.equal(db.tokens.get(201).token_hash, hashCustomerToken(result.data.customer_token));
        assert.equal(db.tokens.get(201).revoked_at, null);
    });
});
