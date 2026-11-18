const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { createAuth } = require('../auth');

function makeDb() {
    const users = [
        { id: 1, nama: 'Admin demo', username: 'admin_demo', password: 'legacy-pass-123', role: 'admin' },
        { id: 2, nama: 'Owner demo', username: 'owner_demo', password: 'owner-pass-123', role: 'owner' },
        { id: 3, nama: 'Teknisi demo', username: 'teknisi_demo', password: 'tech-legacy-pass', role: 'teknisi' }
    ];
    const sessions = new Map();

    return {
        users,
        sessions,
        async execute(sql, params = []) {
            if (sql.includes('FROM users WHERE username = ? AND role = ?')) {
                return [users.filter(user => user.username === params[0] && user.role === params[1]).map(user => ({ ...user }))];
            }
            if (sql.includes('FROM users WHERE id = ?')) {
                return [users.filter(user => user.id === Number(params[0])).map(user => ({ ...user }))];
            }
            if (sql.includes('SELECT id FROM users WHERE username = ? AND id <> ?')) {
                return [users.filter(user => user.username === params[0] && user.id !== Number(params[1])).map(({ id }) => ({ id }))];
            }
            if (sql.includes('DELETE FROM auth_sessions WHERE expires_at <= CURRENT_TIMESTAMP')) {
                for (const [key, session] of sessions) if (session.expiresAt <= new Date()) sessions.delete(key);
                return [{ affectedRows: 0 }];
            }
            if (sql.includes('UPDATE users SET password = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[1]));
                if (user) user.password = params[0];
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('INSERT INTO auth_sessions')) {
                sessions.set(params[0], { tokenHash: params[0], userId: Number(params[1]), expiresAt: new Date(params[2]) });
                return [{ affectedRows: 1 }];
            }
            if (sql.includes('FROM auth_sessions') && sql.includes('JOIN users')) {
                const session = sessions.get(params[0]);
                if (!session || session.expiresAt <= new Date()) return [[]];
                const user = users.find(item => item.id === session.userId);
                return [user ? [{ id: user.id, nama: user.nama, username: user.username, role: user.role }] : []];
            }
            if (sql.includes('DELETE FROM auth_sessions WHERE token_hash = ?')) {
                sessions.delete(params[0]);
                return [{ affectedRows: 1 }];
            }
            if (sql.includes('DELETE FROM auth_sessions WHERE user_id = ?')) {
                for (const [key, session] of sessions) if (session.userId === Number(params[0])) sessions.delete(key);
                return [{ affectedRows: 1 }];
            }
            if (sql.includes('UPDATE users SET username = ?, password = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[2]));
                if (user) Object.assign(user, { username: params[0], password: params[1] });
                return [{ affectedRows: user ? 1 : 0 }];
            }
            throw new Error(`Unexpected SQL in test: ${sql}`);
        }
    };
}

async function withServer(run) {
    const db = makeDb();
    const auth = createAuth(db);
    const app = express();
    app.use(express.json());
    app.use('/api/auth', auth.router);
    app.get('/admin-only', auth.requireRoles(['admin']), (req, res) => res.json({ role: req.authUser.role }));
    app.get('/owner-only', auth.requireRoles(['owner']), (req, res) => res.json({ role: req.authUser.role }));
    app.get('/admin.html', ...auth.requirePageRoles(['admin'], '/admin-login.html'), (req, res) => res.send('admin-page'));
    app.get('/owner.html', ...auth.requirePageRoles(['owner'], '/owner-login.html'), (req, res) => res.send('owner-page'));
    const server = app.listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    try {
        await run({ base, db });
    } finally {
        await new Promise(resolve => server.close(resolve));
    }
}

function cookieFrom(response) {
    const value = response.headers.get('set-cookie');
    assert.ok(value, 'response should set a session cookie');
    return value.split(';', 1)[0];
}

test('login is role-specific and issues an HttpOnly session cookie', async () => {
    await withServer(async ({ base, db }) => {
        const wrongRole = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'admin_demo', password: 'legacy-pass-123', role: 'owner' })
        });
        assert.equal(wrongRole.status, 401);

        const login = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'admin_demo', password: 'legacy-pass-123', role: 'admin' })
        });
        assert.equal(login.status, 200);
        assert.match(db.users[0].password, /^\$scrypt\$/);
        const cookieHeader = login.headers.get('set-cookie');
        assert.match(cookieHeader, /HttpOnly/i);
        assert.match(cookieHeader, /SameSite=Strict/i);
        const cookie = cookieFrom(login);
        const allowed = await fetch(`${base}/admin-only`, { headers: { cookie } });
        assert.equal(allowed.status, 200);
        assert.deepEqual(await allowed.json(), { role: 'admin' });
    });
});

test('role middleware rejects a valid session from another role', async () => {
    await withServer(async ({ base }) => {
        const login = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'admin_demo', password: 'legacy-pass-123', role: 'admin' })
        });
        const denied = await fetch(`${base}/owner-only`, { headers: { cookie: cookieFrom(login) } });
        assert.equal(denied.status, 403);
    });
});

test('account update verifies current password, changes username/password, and rotates sessions', async () => {
    await withServer(async ({ base, db }) => {
        const login = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'owner_demo', password: 'owner-pass-123', role: 'owner' })
        });
        const oldCookie = cookieFrom(login);
        const change = await fetch(`${base}/api/auth/account`, {
            method: 'PATCH', headers: { 'content-type': 'application/json', cookie: oldCookie },
            body: JSON.stringify({ currentPassword: 'owner-pass-123', newUsername: 'owner_new', newPassword: 'new-secret-12345' })
        });
        assert.equal(change.status, 200);
        const newCookie = cookieFrom(change);
        assert.notEqual(newCookie, oldCookie);
        assert.ok(db.users[1].password.startsWith('$scrypt$'));
        assert.notEqual(db.users[1].password, 'new-secret-12345');

        const oldSession = await fetch(`${base}/owner-only`, { headers: { cookie: oldCookie } });
        assert.equal(oldSession.status, 401);
        const oldLogin = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'owner_new', password: 'owner-pass-123', role: 'owner' })
        });
        assert.equal(oldLogin.status, 401);
        const newLogin = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'owner_new', password: 'new-secret-12345', role: 'owner' })
        });
        assert.equal(newLogin.status, 200);
    });
});

test('admin cannot change their own credentials', async () => {
    await withServer(async ({ base }) => {
        const login = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'admin_demo', password: 'legacy-pass-123', role: 'admin' })
        });
        const denied = await fetch(`${base}/api/auth/account`, {
            method: 'PATCH', headers: { 'content-type': 'application/json', cookie: cookieFrom(login) },
            body: JSON.stringify({ currentPassword: 'legacy-pass-123', newUsername: 'self_changed' })
        });
        assert.equal(denied.status, 403);

        const technicianLogin = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'teknisi_demo', password: 'tech-legacy-pass', role: 'teknisi' })
        });
        const technicianDenied = await fetch(`${base}/api/auth/account`, {
            method: 'PATCH', headers: { 'content-type': 'application/json', cookie: cookieFrom(technicianLogin) },
            body: JSON.stringify({ currentPassword: 'tech-legacy-pass', newUsername: 'tech_changed' })
        });
        assert.equal(technicianDenied.status, 403);
    });
});

test('protected pages redirect signed-out users and keep roles apart', async () => {
    await withServer(async ({ base }) => {
        const signedOut = await fetch(`${base}/admin.html`, { redirect: 'manual' });
        assert.equal(signedOut.status, 302);
        assert.equal(signedOut.headers.get('location'), '/admin-login.html');

        const login = await fetch(`${base}/api/auth/login`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ username: 'admin_demo', password: 'legacy-pass-123', role: 'admin' })
        });
        const wrongPanel = await fetch(`${base}/owner.html`, {
            redirect: 'manual', headers: { cookie: cookieFrom(login) }
        });
        assert.equal(wrongPanel.status, 302);
        assert.equal(wrongPanel.headers.get('location'), '/admin.html');
    });
});
