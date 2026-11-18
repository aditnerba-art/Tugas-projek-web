const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { createAdminServiceDeleteRouter } = require('../routes/admin-service-delete');

function makeDb() {
    const services = new Map([
        [10, { id: 10, customer_id: 1 }],
        [20, { id: 20, customer_id: 2 }]
    ]);
    const serviceDetails = new Map([[31, { service_id: 10 }], [32, { service_id: 20 }]]);
    const serviceLogs = new Map([[41, { service_id: 10 }], [42, { service_id: 20 }]]);
    const accessTokens = new Map([
        [100, { id: 100, service_id: 10 }],
        [200, { id: 200, service_id: 20 }]
    ]);
    const sessions = new Map([
        ['session-10', { access_token_id: 100 }],
        ['session-20', { access_token_id: 200 }]
    ]);
    const transaction = { begun: 0, committed: 0, rolledBack: 0, queries: [] };

    return {
        services, serviceDetails, serviceLogs, accessTokens, sessions, transaction,
        async getConnection() {
            return {
                beginTransaction: async () => { transaction.begun++; },
                commit: async () => { transaction.committed++; },
                rollback: async () => { transaction.rolledBack++; },
                release: () => {},
                async execute(sql, params = []) {
                    transaction.queries.push(sql.replace(/\s+/g, ' ').trim());
                    const id = Number(params[0]);
                    if (sql.includes('SELECT id FROM services WHERE id = ? FOR UPDATE')) {
                        const service = services.get(id);
                        return [service ? [{ id: service.id }] : []];
                    }
                    if (sql.includes('DELETE cs FROM customer_sessions')) {
                        for (const [hash, session] of sessions) {
                            const token = accessTokens.get(session.access_token_id);
                            if (token && params.includes(token.service_id)) sessions.delete(hash);
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
                    throw new Error(`Unexpected transaction query: ${sql}`);
                }
            };
        }
    };
}

async function withServer(run) {
    const db = makeDb();
    const app = express();
    app.use('/api/admin', createAdminServiceDeleteRouter(db));
    const server = app.listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    const base = `http://127.0.0.1:${server.address().port}/api/admin/service`;
    try { await run({ base, db }); }
    finally { await new Promise(resolve => server.close(resolve)); }
}

test('admin can permanently delete a service and its dependent records transactionally', async () => {
    await withServer(async ({ base, db }) => {
        const response = await fetch(`${base}/10`, { method: 'DELETE' });
        assert.equal(response.status, 200);
        assert.deepEqual(await response.json(), { success: true, message: 'Data servis berhasil dihapus' });
        assert.equal(db.services.has(10), false);
        assert.equal(db.serviceDetails.has(31), false);
        assert.equal(db.serviceLogs.has(41), false);
        assert.equal(db.accessTokens.has(100), false);
        assert.equal(db.sessions.has('session-10'), false);
        assert.equal(db.services.has(20), true);
        assert.equal(db.serviceDetails.has(32), true);
        assert.equal(db.sessions.has('session-20'), true);
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
    });
});

test('service deletion validates IDs and rolls back when the service is missing', async () => {
    await withServer(async ({ base, db }) => {
        assert.equal((await fetch(`${base}/invalid`, { method: 'DELETE' })).status, 400);
        const response = await fetch(`${base}/999`, { method: 'DELETE' });
        assert.equal(response.status, 404);
        assert.deepEqual(await response.json(), { success: false, message: 'Servis tidak ditemukan' });
        assert.deepEqual(
            [db.transaction.begun, db.transaction.committed, db.transaction.rolledBack],
            [1, 0, 1]
        );
    });
});
