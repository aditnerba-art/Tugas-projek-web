const express = require('express');
const crypto = require('node:crypto');
const { promisify } = require('node:util');

const scryptAsync = promisify(crypto.scrypt);
const SESSION_COOKIE = 'servis_session';
const SESSION_TTL_MS = 8 * 60 * 60 * 1000;
const VALID_ROLES = new Set(['admin', 'teknisi', 'owner']);
const SCRYPT = { N: 16384, r: 8, p: 1, keyLength: 64 };

async function hashPassword(password) {
    if (typeof password !== 'string' || Buffer.byteLength(password, 'utf8') > 1024) {
        throw new TypeError('Password tidak valid');
    }
    const salt = crypto.randomBytes(16);
    const derived = await scryptAsync(password, salt, SCRYPT.keyLength, {
        N: SCRYPT.N, r: SCRYPT.r, p: SCRYPT.p, maxmem: 64 * 1024 * 1024
    });
    return `$scrypt$${SCRYPT.N}$${SCRYPT.r}$${SCRYPT.p}$${salt.toString('hex')}$${derived.toString('hex')}`;
}

function isPasswordHash(value) {
    return typeof value === 'string' && value.startsWith('$scrypt$');
}

async function verifyPassword(password, storedValue) {
    if (typeof password !== 'string' || typeof storedValue !== 'string' || Buffer.byteLength(password, 'utf8') > 1024) {
        return false;
    }

    if (!isPasswordHash(storedValue)) {
        const actual = Buffer.from(password, 'utf8');
        const expected = Buffer.from(storedValue, 'utf8');
        return actual.length === expected.length && crypto.timingSafeEqual(actual, expected);
    }

    const parts = storedValue.split('$');
    if (parts.length !== 7 || parts[1] !== 'scrypt') return false;
    const N = Number(parts[2]);
    const r = Number(parts[3]);
    const p = Number(parts[4]);
    const salt = Buffer.from(parts[5], 'hex');
    const expected = Buffer.from(parts[6], 'hex');
    if (!Number.isInteger(N) || !Number.isInteger(r) || !Number.isInteger(p) ||
        N < 2 || N > 65536 || r < 1 || r > 16 || p < 1 || p > 4 ||
        salt.length !== 16 || expected.length !== SCRYPT.keyLength) return false;

    try {
        const actual = await scryptAsync(password, salt, expected.length, {
            N, r, p, maxmem: 64 * 1024 * 1024
        });
        return crypto.timingSafeEqual(actual, expected);
    } catch {
        return false;
    }
}

function sha256(value) {
    return crypto.createHash('sha256').update(value).digest('hex');
}

function readCookie(req) {
    const header = req.headers.cookie || '';
    for (const part of header.split(';')) {
        const separator = part.indexOf('=');
        if (separator < 0) continue;
        if (part.slice(0, separator).trim() === SESSION_COOKIE) {
            return part.slice(separator + 1).trim();
        }
    }
    return '';
}

function setSessionCookie(res, req, token) {
    const parts = [
        `${SESSION_COOKIE}=${token}`,
        'Path=/',
        `Max-Age=${Math.floor(SESSION_TTL_MS / 1000)}`,
        'HttpOnly',
        'SameSite=Strict'
    ];
    if (req.secure || process.env.NODE_ENV === 'production') parts.push('Secure');
    res.setHeader('Set-Cookie', parts.join('; '));
}

function clearSessionCookie(res, req) {
    const parts = [`${SESSION_COOKIE}=`, 'Path=/', 'Max-Age=0', 'HttpOnly', 'SameSite=Strict'];
    if (req.secure || process.env.NODE_ENV === 'production') parts.push('Secure');
    res.setHeader('Set-Cookie', parts.join('; '));
}

function publicUser(user) {
    return { id: user.id, nama: user.nama, username: user.username, role: user.role };
}

function createAuth(db) {
    const router = express.Router();

    async function authenticate(req, res, next) {
        const token = readCookie(req);
        if (!/^[a-f0-9]{64}$/i.test(token)) {
            return res.status(401).json({ status: 'error', message: 'Silakan login terlebih dahulu' });
        }
        try {
            const [rows] = await db.execute(
                `SELECT u.id, u.nama, u.username, u.role
                 FROM auth_sessions s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.token_hash = ? AND s.expires_at > CURRENT_TIMESTAMP
                 LIMIT 1`,
                [sha256(token)]
            );
            if (!rows.length) {
                clearSessionCookie(res, req);
                return res.status(401).json({ status: 'error', message: 'Sesi login sudah berakhir' });
            }
            req.authUser = rows[0];
            req.authTokenHash = sha256(token);
            return next();
        } catch (error) {
            console.error('Auth session lookup failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Gagal memeriksa sesi login' });
        }
    }

    function requireRoles(roles) {
        const allowed = new Set(Array.isArray(roles) ? roles : [roles]);
        return [authenticate, (req, res, next) => {
            if (!allowed.has(req.authUser.role)) {
                return res.status(403).json({ status: 'error', message: 'Akses tidak diizinkan untuk peran ini' });
            }
            return next();
        }];
    }

    function requirePageRoles(roles, loginPath = '/login.html') {
        const allowed = new Set(Array.isArray(roles) ? roles : [roles]);
        return [async (req, res, next) => {
            const token = readCookie(req);
            if (!/^[a-f0-9]{64}$/i.test(token)) return res.redirect(302, loginPath);
            try {
                const [rows] = await db.execute(
                    `SELECT u.id, u.nama, u.username, u.role
                     FROM auth_sessions s JOIN users u ON u.id = s.user_id
                     WHERE s.token_hash = ? AND s.expires_at > CURRENT_TIMESTAMP LIMIT 1`,
                    [sha256(token)]
                );
                if (!rows.length) {
                    clearSessionCookie(res, req);
                    return res.redirect(302, loginPath);
                }
                req.authUser = rows[0];
                if (!allowed.has(req.authUser.role)) {
                    if (VALID_ROLES.has(req.authUser.role)) return res.redirect(302, `/${req.authUser.role}.html`);
                    return res.status(403).send('Akses tidak diizinkan.');
                }
                return next();
            } catch (error) {
                console.error('Page auth lookup failed:', error.message);
                return res.status(500).send('Gagal memeriksa sesi login.');
            }
        }];
    }

    async function createSession(userId, req, res) {
        const token = crypto.randomBytes(32).toString('hex');
        const expiresAt = new Date(Date.now() + SESSION_TTL_MS);
        await db.execute(
            'INSERT INTO auth_sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)',
            [sha256(token), userId, expiresAt]
        );
        setSessionCookie(res, req, token);
    }

    router.post('/login', async (req, res) => {
        const username = typeof req.body?.username === 'string' ? req.body.username.trim() : '';
        const password = typeof req.body?.password === 'string' ? req.body.password : '';
        const role = typeof req.body?.role === 'string' ? req.body.role.trim().toLowerCase() : '';
        if (!username || !password || !VALID_ROLES.has(role) || Buffer.byteLength(password, 'utf8') > 1024) {
            return res.status(400).json({ status: 'error', message: 'Username, kata sandi, dan peran wajib diisi' });
        }
        try {
            const [rows] = await db.execute(
                'SELECT id, nama, username, password, role FROM users WHERE username = ? AND role = ? LIMIT 1',
                [username, role]
            );
            const user = rows[0];
            if (!user || !(await verifyPassword(password, user.password))) {
                return res.status(401).json({ status: 'error', message: 'Username atau kata sandi salah' });
            }
            if (!isPasswordHash(user.password)) {
                const upgradedHash = await hashPassword(user.password);
                await db.execute('UPDATE users SET password = ? WHERE id = ?', [upgradedHash, user.id]);
            }
            await db.execute('DELETE FROM auth_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
            await createSession(user.id, req, res);
            return res.json({ status: 'success', user: publicUser(user) });
        } catch (error) {
            console.error('Login failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Login gagal diproses' });
        }
    });

    router.post('/logout', async (req, res) => {
        const token = readCookie(req);
        try {
            if (/^[a-f0-9]{64}$/i.test(token)) {
                await db.execute('DELETE FROM auth_sessions WHERE token_hash = ?', [sha256(token)]);
            }
            clearSessionCookie(res, req);
            return res.json({ status: 'success' });
        } catch (error) {
            console.error('Logout failed:', error.message);
            clearSessionCookie(res, req);
            return res.status(500).json({ status: 'error', message: 'Logout gagal diproses' });
        }
    });

    router.get('/me', ...requireRoles(['admin', 'teknisi', 'owner']), (req, res) => {
        res.json({ status: 'success', user: publicUser(req.authUser) });
    });

    router.patch('/account', ...requireRoles(['owner']), async (req, res) => {
        const currentPassword = typeof req.body?.currentPassword === 'string' ? req.body.currentPassword : '';
        const newUsername = typeof req.body?.newUsername === 'string' ? req.body.newUsername.trim() : '';
        const newPassword = typeof req.body?.newPassword === 'string' ? req.body.newPassword : '';
        if (!currentPassword) {
            return res.status(400).json({ status: 'error', message: 'Kata sandi saat ini wajib diisi' });
        }
        if (newUsername && !/^[a-zA-Z0-9_.-]{3,32}$/.test(newUsername)) {
            return res.status(400).json({ status: 'error', message: 'Username harus 3–32 karakter: huruf, angka, titik, garis bawah, atau tanda hubung' });
        }
        if (newPassword && (newPassword.length < 10 || Buffer.byteLength(newPassword, 'utf8') > 1024)) {
            return res.status(400).json({ status: 'error', message: 'Kata sandi baru minimal 10 karakter' });
        }

        try {
            const [rows] = await db.execute(
                'SELECT id, nama, username, password, role FROM users WHERE id = ? LIMIT 1',
                [req.authUser.id]
            );
            const user = rows[0];
            if (!user || !(await verifyPassword(currentPassword, user.password))) {
                return res.status(401).json({ status: 'error', message: 'Kata sandi saat ini salah' });
            }

            const username = newUsername || user.username;
            const passwordChanged = Boolean(newPassword);
            if (username === user.username && !passwordChanged) {
                return res.status(400).json({ status: 'error', message: 'Masukkan username atau kata sandi baru' });
            }
            if (username !== user.username) {
                const [duplicates] = await db.execute(
                    'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1',
                    [username, user.id]
                );
                if (duplicates.length) {
                    return res.status(409).json({ status: 'error', message: 'Username sudah digunakan' });
                }
            }

            const passwordHash = passwordChanged ? await hashPassword(newPassword) : user.password;
            await db.execute('UPDATE users SET username = ?, password = ? WHERE id = ?', [username, passwordHash, user.id]);
            await db.execute('DELETE FROM auth_sessions WHERE user_id = ?', [user.id]);
            await createSession(user.id, req, res);
            return res.json({ status: 'success', user: publicUser({ ...user, username }) });
        } catch (error) {
            if (error.code === 'ER_DUP_ENTRY') {
                return res.status(409).json({ status: 'error', message: 'Username sudah digunakan' });
            }
            console.error('Account update failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Perubahan akun gagal disimpan' });
        }
    });

    return { router, requireRoles, requirePageRoles, authenticate };
}

module.exports = { createAuth, hashPassword, verifyPassword, isPasswordHash };
