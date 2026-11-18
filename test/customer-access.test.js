const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { generateCustomerToken, hashCustomerToken, customerPortalUrl } = require('../lib/customer-token');
const { createCustomerRouter } = require('../routes/customer');

function makeDb() {
    const token = '01234';
    const legacyToken = 'A1b2C3d4E5f6G7h8';
    const tokens = new Map([
        [hashCustomerToken(token), { id: 5, service_id: 101, expires_at: new Date(Date.now() + 86400000), revoked_at: null }],
        [hashCustomerToken(legacyToken), { id: 6, service_id: 101, expires_at: new Date(Date.now() + 86400000), revoked_at: null }]
    ]);
    const sessions = new Map();
    const services = new Map([
        [101, { id: 101, no_nota: 'SRV-261030-00Z', status: 'menunggu_persetujuan', total_biaya: 100000, nama_pelanggan: 'Dewi' }],
        [999, { id: 999, status: 'menunggu_persetujuan', total_biaya: 250000, nama_pelanggan: 'Other' }]
    ]);
    async function execute(sql, params = []) {
        if (sql.includes('FROM service_access_tokens') && sql.includes('token_hash = ?')) {
            const access = tokens.get(params[0]);
            return [access && access.expires_at > new Date() ? [{ id: access.id, service_id: access.service_id, nama_pelanggan: services.get(access.service_id)?.nama_pelanggan }] : []];
        }
        if (sql.includes('INSERT INTO customer_sessions')) {
            sessions.set(params[0], { access_token_id: Number(params[1]), expires_at: new Date(params[2]) });
            return [{ affectedRows: 1 }];
        }
        if (sql.includes('FROM customer_sessions cs') && sql.includes('JOIN service_access_tokens at')) {
            const session = sessions.get(params[0]);
            if (!session || session.expires_at <= new Date()) return [[]];
            const access = [...tokens.values()].find(item => item.id === session.access_token_id);
            return [access ? [{ access_token_id: access.id, service_id: access.service_id }] : []];
        }
        if (sql.includes('FROM services s') && sql.includes('JOIN customers c')) {
            const service = services.get(Number(params[0]));
            return [service ? [{ ...service, jenis_unit: 'Laptop', merek_tipe: 'Lenovo', keluhan: 'Layar mati', biaya_jasa: 0, status_pembayaran: 'belum_bayar', created_at: new Date(), nama_teknisi: null }] : []];
        }
        if (sql.includes('FROM service_details sd')) return [[]];
        if (sql.includes('FROM service_logs')) return [[]];
        if (sql.includes('DELETE FROM customer_sessions WHERE session_hash = ?')) {
            sessions.delete(params[0]); return [{ affectedRows: 1 }];
        }
        throw new Error(`Unexpected SQL: ${sql}`);
    }
    return {
        token, legacyToken, tokens, sessions, services, execute,
        async getConnection() {
            return {
                beginTransaction: async () => {}, commit: async () => {}, rollback: async () => {}, release: () => {},
                execute: async (sql, params = []) => {
                    if (sql.includes('SELECT id, status, total_biaya FROM services WHERE id = ? FOR UPDATE')) {
                        const service = services.get(Number(params[0]));
                        return [service ? [{ id: service.id, status: service.status, total_biaya: service.total_biaya }] : []];
                    }
                    if (sql.includes('UPDATE services SET status = ? WHERE id = ?')) {
                        const service = services.get(Number(params[1]));
                        if (service) service.status = params[0];
                        return [{ affectedRows: service ? 1 : 0 }];
                    }
                    if (sql.includes('INSERT INTO service_logs')) return [{ affectedRows: 1 }];
                    throw new Error(`Unexpected transaction SQL: ${sql}`);
                }
            };
        }
    };
}

async function withServer(run) {
    const db = makeDb();
    const app = express();
    app.use(express.json());
    app.use('/api/customer', createCustomerRouter(db));
    const server = app.listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    try { await run({ base, db }); }
    finally { await new Promise(resolve => server.close(resolve)); }
}

function cookieFrom(response) {
    const header = response.headers.get('set-cookie');
    assert.ok(header);
    return header.split(';', 1)[0];
}

test('customer portal links require a public HTTPS domain', () => {
    assert.equal(customerPortalUrl(''), null);
    assert.equal(customerPortalUrl('http://localhost:3000'), null);
    assert.equal(customerPortalUrl('https://localhost:3000'), null);
    assert.equal(customerPortalUrl('https://servis.example.com/shop/'), 'https://servis.example.com/shop/index.html');
});

test('customer access codes are five-digit numeric and stored as a hash', () => {
    const token = generateCustomerToken();
    assert.match(token, /^\d{5}$/);
    assert.match(hashCustomerToken(token), /^[a-f0-9]{64}$/);
    assert.notEqual(hashCustomerToken(token), token);
});

test('customer logs in with the token, not an invoice number', async () => {
    await withServer(async ({ base, db }) => {
        const login = await fetch(`${base}/api/customer/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token: db.token, customer_name: 'Dewi' })
        });
        assert.equal(login.status, 200);
        assert.match(login.headers.get('set-cookie'), /HttpOnly/i);
        const cookie = cookieFrom(login);
        const data = await fetch(`${base}/api/customer/me`, { headers: { cookie } });
        assert.equal(data.status, 200);
        assert.equal((await data.json()).data.unit.nama_pelanggan, 'Dewi');
    });
});

test('customer login requires the service customer name to match the access code', async () => {
    await withServer(async ({ base, db }) => {
        for (const customer_name of ['', 'Nama Salah']) {
            const rejected = await fetch(`${base}/api/customer/login`, {
                method: 'POST', headers: { 'content-type': 'application/json' },
                body: JSON.stringify({ token: db.token, customer_name })
            });
            assert.equal(rejected.status, 401);
            assert.equal(rejected.headers.get('set-cookie'), null);
        }

        const accepted = await fetch(`${base}/api/customer/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token: db.token, customer_name: '  DEWI  ' })
        });
        assert.equal(accepted.status, 200);
        assert.match(accepted.headers.get('set-cookie'), /HttpOnly/i);
    });
});

test('legacy alphanumeric customer access codes remain usable', async () => {
    await withServer(async ({ base, db }) => {
        const login = await fetch(`${base}/api/customer/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token: db.legacyToken, customer_name: 'Dewi' })
        });
        assert.equal(login.status, 200);
        assert.match(login.headers.get('set-cookie'), /HttpOnly/i);
    });
});

test('customer login throttles repeated failed access-code attempts', async () => {
    await withServer(async ({ base, db }) => {
        for (let attempt = 0; attempt < 5; attempt++) {
            const response = await fetch(`${base}/api/customer/login`, {
                method: 'POST', headers: { 'content-type': 'application/json' },
                body: JSON.stringify({ token: '99999', customer_name: 'Dewi' })
            });
            assert.equal(response.status, 401);
        }
        const limited = await fetch(`${base}/api/customer/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token: db.token, customer_name: 'Dewi' })
        });
        assert.equal(limited.status, 429);
        assert.ok(Number(limited.headers.get('retry-after')) > 0);
    });
});

test('customer approval updates only the service attached to the token', async () => {
    await withServer(async ({ base, db }) => {
        const login = await fetch(`${base}/api/customer/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token: db.token, customer_name: 'Dewi' })
        });
        const response = await fetch(`${base}/api/customer/approval`, {
            method: 'POST', headers: { 'content-type': 'application/json', cookie: cookieFrom(login) },
            body: JSON.stringify({ service_id: 999, no_nota: 'guess', status_baru: 'dikerjakan' })
        });
        assert.equal(response.status, 200);
        assert.equal(db.services.get(101).status, 'dikerjakan');
        assert.equal(db.services.get(999).status, 'menunggu_persetujuan');
    });
});
