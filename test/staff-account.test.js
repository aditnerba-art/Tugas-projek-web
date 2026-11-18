const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { createAuth } = require('../auth');
const { createOwnerStaffRouter } = require('../routes/owner-staff');

function makeDb() {
    const users = [
        { id: 1, nama: 'Admin', username: 'admin1', password: 'admin-old-pass', role: 'admin' },
        { id: 2, nama: 'Teknisi', username: 'teknisi1', password: 'teknisi-old-pass', role: 'teknisi' },
        { id: 3, nama: 'Owner', username: 'owner1', password: 'owner-old-pass', role: 'owner' }
    ];
    const sessions = new Map();
    return {
        users,
        sessions,
        async execute(sql, params = []) {
            if (sql.includes('FROM users WHERE username = ? AND role = ?')) {
                return [users.filter(user => user.username === params[0] && user.role === params[1]).map(user => ({ ...user }))];
            }
            if (sql.includes('UPDATE users SET password = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[1]));
                if (user) user.password = params[0];
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('DELETE FROM auth_sessions WHERE expires_at <= CURRENT_TIMESTAMP')) return [{ affectedRows: 0 }];
            if (sql.includes('INSERT INTO auth_sessions')) {
                sessions.set(params[0], { userId: Number(params[1]), expiresAt: new Date(params[2]) });
                return [{ affectedRows: 1 }];
            }
            if (sql.includes('FROM auth_sessions') && sql.includes('JOIN users')) {
                const session = sessions.get(params[0]);
                if (!session || session.expiresAt <= new Date()) return [[]];
                const user = users.find(item => item.id === session.userId);
                return [user ? [{ id: user.id, nama: user.nama, username: user.username, role: user.role }] : []];
            }
            if (sql.includes('FROM users') && sql.includes("WHERE role IN ('admin', 'teknisi')")) {
                return [users.filter(user => ['admin', 'teknisi'].includes(user.role)).map(({ id, nama, username, role }) => ({ id, nama, username, role }))];
            }
            if (sql.includes('WHERE id = ? AND role IN')) {
                const user = users.find(item => item.id === Number(params[0]) && ['admin', 'teknisi'].includes(item.role));
                return [user ? [{ id: user.id, nama: user.nama, username: user.username, role: user.role }] : []];
            }
            if (sql.includes('SELECT id FROM users WHERE username = ? AND id <> ?')) {
                return [users.filter(user => user.username === params[0] && user.id !== Number(params[1])).map(({ id }) => ({ id }))];
            }
            if (sql.includes('WHERE id = ? FOR UPDATE')) {
                const user = users.find(item => item.id === Number(params[0]));
                return [user ? [{ id: user.id, nama: user.nama, username: user.username, role: user.role }] : []];
            }
            if (sql.includes('SELECT id FROM users WHERE username = ? LIMIT 1')) {
                return [users.filter(user => user.username === params[0]).map(({ id }) => ({ id }))];
            }
            if (sql.includes('INSERT INTO users')) {
                const newId = users.length > 0 ? Math.max(...users.map(u => u.id)) + 1 : 1;
                const newUser = { id: newId, nama: params[0], username: params[1], password: params[2], role: params[3] };
                users.push(newUser);
                return [{ insertId: newId, affectedRows: 1 }];
            }
            if (sql.includes('UPDATE users SET nama = ?, username = ?, password = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[3]));
                if (user) Object.assign(user, { nama: params[0], username: params[1], password: params[2] });
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('UPDATE users SET nama = ?, username = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[2]));
                if (user) Object.assign(user, { nama: params[0], username: params[1] });
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('UPDATE users SET username = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[1]));
                if (user) user.username = params[0];
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('UPDATE users SET username = ?, password = ? WHERE id = ?')) {
                const user = users.find(item => item.id === Number(params[2]));
                if (user) Object.assign(user, { username: params[0], password: params[1] });
                return [{ affectedRows: user ? 1 : 0 }];
            }
            if (sql.includes('DELETE FROM auth_sessions WHERE user_id = ?')) {
                for (const [token, session] of sessions) if (session.userId === Number(params[0])) sessions.delete(token);
                return [{ affectedRows: 1 }];
            }
            if (sql.includes('UPDATE services SET teknisi_id = NULL WHERE teknisi_id = ?')) {
                return [{ affectedRows: 0 }];
            }
            if (sql.includes('UPDATE purchases SET created_by = NULL WHERE created_by = ?')) {
                return [{ affectedRows: 0 }];
            }
            if (sql.includes('DELETE FROM users WHERE id = ?')) {
                const idx = users.findIndex(u => u.id === Number(params[0]));
                if (idx !== -1) users.splice(idx, 1);
                return [{ affectedRows: idx !== -1 ? 1 : 0 }];
            }
            throw new Error(`Unexpected SQL in test: ${sql}`);
        },
        async getConnection() {
            return {
                beginTransaction: async () => {},
                commit: async () => {},
                rollback: async () => {},
                release: () => {},
                query: async (sql, params = []) => {
                    return this.execute ? this.execute(sql, params) : makeDb().execute(sql, params);
                },
                execute: async (sql, params = []) => {
                    return this.execute ? this.execute(sql, params) : makeDb().execute(sql, params);
                }
            };
        }
    };
}

async function withServer(run) {
    const db = makeDb();
    const auth = createAuth(db);
    const app = express();
    app.use(express.json());
    app.use('/api/auth', auth.router);
    app.use('/api/owner/staff', ...auth.requireRoles(['owner']), createOwnerStaffRouter(db));
    const server = app.listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    try { await run({ base, db }); }
    finally { await new Promise(resolve => server.close(resolve)); }
}

async function login(base, username, password, role) {
    const response = await fetch(`${base}/api/auth/login`, {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ username, password, role })
    });
    return { response, cookie: response.headers.get('set-cookie')?.split(';', 1)[0] };
}

test('only owner can list and change admin/technician credentials', async () => {
    await withServer(async ({ base, db }) => {
        const adminSession = await login(base, 'admin1', 'admin-old-pass', 'admin');
        const denied = await fetch(`${base}/api/owner/staff`, { headers: { cookie: adminSession.cookie } });
        assert.equal(denied.status, 403);

        const ownerSession = await login(base, 'owner1', 'owner-old-pass', 'owner');
        const listed = await fetch(`${base}/api/owner/staff`, { headers: { cookie: ownerSession.cookie } });
        assert.equal(listed.status, 200);
        const list = await listed.json();
        assert.deepEqual(list.users.map(user => user.role), ['admin', 'teknisi']);
        assert.equal(JSON.stringify(list).includes('password'), false);

        const changed = await fetch(`${base}/api/owner/staff/1/credentials`, {
            method: 'PATCH', headers: { 'content-type': 'application/json', cookie: ownerSession.cookie },
            body: JSON.stringify({ newUsername: 'admin2', newPassword: 'admin-new-pass-123' })
        });
        assert.equal(changed.status, 200);
        assert.match(db.users[0].password, /^\$scrypt\$/);
        const revoked = await fetch(`${base}/api/auth/me`, { headers: { cookie: adminSession.cookie } });
        assert.equal(revoked.status, 401);

        const oldLogin = await login(base, 'admin1', 'admin-old-pass', 'admin');
        assert.equal(oldLogin.response.status, 401);
        const newLogin = await login(base, 'admin2', 'admin-new-pass-123', 'admin');
        assert.equal(newLogin.response.status, 200);

        const ownerEdit = await fetch(`${base}/api/owner/staff/3/credentials`, {
            method: 'PATCH', headers: { 'content-type': 'application/json', cookie: ownerSession.cookie },
            body: JSON.stringify({ newUsername: 'owner2' })
        });
        assert.equal(ownerEdit.status, 404);
    });
});

test('owner can create, edit nama, and delete staff accounts', async () => {
    await withServer(async ({ base, db }) => {
        const ownerSession = await login(base, 'owner1', 'owner-old-pass', 'owner');

        // 1. Tambah Staf Baru (Admin)
        const addAdminRes = await fetch(`${base}/api/owner/staff`, {
            method: 'POST',
            headers: { 'content-type': 'application/json', cookie: ownerSession.cookie },
            body: JSON.stringify({
                nama: 'Admin Baru',
                username: 'admin_baru',
                password: 'password123',
                role: 'admin'
            })
        });
        assert.equal(addAdminRes.status, 201);
        const addAdminJson = await addAdminRes.json();
        assert.equal(addAdminJson.user.nama, 'Admin Baru');
        assert.equal(addAdminJson.user.role, 'admin');

        // 2. Tolak penambahan role tidak valid
        const invalidRoleRes = await fetch(`${base}/api/owner/staff`, {
            method: 'POST',
            headers: { 'content-type': 'application/json', cookie: ownerSession.cookie },
            body: JSON.stringify({
                nama: 'Hacker',
                username: 'hacker_role',
                password: 'password123',
                role: 'owner'
            })
        });
        assert.equal(invalidRoleRes.status, 400);

        // 3. Edit nama staf
        const editRes = await fetch(`${base}/api/owner/staff/${addAdminJson.user.id}`, {
            method: 'PATCH',
            headers: { 'content-type': 'application/json', cookie: ownerSession.cookie },
            body: JSON.stringify({
                nama: 'Admin Updated',
                username: 'admin_baru_edit'
            })
        });
        assert.equal(editRes.status, 200);
        const editJson = await editRes.json();
        assert.equal(editJson.user.nama, 'Admin Updated');
        assert.equal(editJson.user.username, 'admin_baru_edit');

        // 4. Cegah Owner menghapus akun sendiri
        const delSelfRes = await fetch(`${base}/api/owner/staff/3`, {
            method: 'DELETE',
            headers: { cookie: ownerSession.cookie }
        });
        assert.equal(delSelfRes.status, 400);

        // 5. Hapus akun staf yang baru dibuat
        const delRes = await fetch(`${base}/api/owner/staff/${addAdminJson.user.id}`, {
            method: 'DELETE',
            headers: { cookie: ownerSession.cookie }
        });
        assert.equal(delRes.status, 200);

        // Pastikan sudah terhapus dari users
        assert.equal(db.users.some(u => u.id === addAdminJson.user.id), false);
    });
});
