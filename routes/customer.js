const express = require('express');
const crypto = require('node:crypto');
const { hashCustomerToken } = require('../lib/customer-token');

const CUSTOMER_COOKIE = 'customer_session';
const SESSION_TTL_MS = 7 * 24 * 60 * 60 * 1000;
const CUSTOMER_LOGIN_ATTEMPT_LIMIT = 5;
const CUSTOMER_LOGIN_WINDOW_MS = 15 * 60 * 1000;
const MAX_LOGIN_ATTEMPT_BUCKETS = 5000;

function normalizeCustomerName(value) {
    return typeof value === 'string'
        ? value.normalize('NFC').trim().replace(/\s+/g, ' ').toLocaleLowerCase('id-ID')
        : '';
}

function readCookie(req) {
    for (const part of (req.headers.cookie || '').split(';')) {
        const index = part.indexOf('=');
        if (index >= 0 && part.slice(0, index).trim() === CUSTOMER_COOKIE) return part.slice(index + 1).trim();
    }
    return '';
}

function setCookie(res, req, value, maxAge) {
    const parts = [`${CUSTOMER_COOKIE}=${value}`, 'Path=/', `Max-Age=${maxAge}`, 'HttpOnly', 'SameSite=Strict'];
    if (req.secure || process.env.NODE_ENV === 'production') parts.push('Secure');
    res.setHeader('Set-Cookie', parts.join('; '));
}

function clearCookie(res, req) {
    setCookie(res, req, '', 0);
}

function createCustomerRouter(db) {
    const router = express.Router();
    const customerLoginAttempts = new Map();

    function recordFailedLogin(key, now) {
        const current = customerLoginAttempts.get(key);
        const attempt = current && current.resetAt > now
            ? { ...current, count: current.count + 1 }
            : { count: 1, resetAt: now + CUSTOMER_LOGIN_WINDOW_MS };
        customerLoginAttempts.delete(key);
        customerLoginAttempts.set(key, attempt);
        while (customerLoginAttempts.size > MAX_LOGIN_ATTEMPT_BUCKETS) {
            customerLoginAttempts.delete(customerLoginAttempts.keys().next().value);
        }
        return attempt;
    }

    async function requireCustomer(req, res, next) {
        const token = readCookie(req);
        if (!/^[a-f0-9]{64}$/i.test(token)) {
            return res.status(401).json({ status: 'error', message: 'Masukkan kode akses pelanggan untuk melanjutkan' });
        }
        try {
            const [rows] = await db.execute(
                `SELECT at.id AS access_token_id, at.service_id
                 FROM customer_sessions cs
                 JOIN service_access_tokens at ON at.id = cs.access_token_id
                 WHERE cs.session_hash = ? AND cs.expires_at > CURRENT_TIMESTAMP
                   AND at.revoked_at IS NULL AND at.expires_at > CURRENT_TIMESTAMP
                 LIMIT 1`,
                [crypto.createHash('sha256').update(token).digest('hex')]
            );
            if (!rows.length) {
                clearCookie(res, req);
                return res.status(401).json({ status: 'error', message: 'Sesi pelanggan berakhir. Masukkan kode akses lagi.' });
            }
            req.customerAccess = rows[0];
            return next();
        } catch (error) {
            console.error('Customer session lookup failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Sesi pelanggan gagal diperiksa' });
        }
    }

    async function tryCustomerAuth(req, res, next) {
        const token = readCookie(req);
        if (/^[a-f0-9]{64}$/i.test(token)) {
            try {
                const [rows] = await db.execute(
                    `SELECT at.id AS access_token_id, at.service_id
                     FROM customer_sessions cs
                     JOIN service_access_tokens at ON at.id = cs.access_token_id
                     WHERE cs.session_hash = ? AND cs.expires_at > CURRENT_TIMESTAMP
                       AND at.revoked_at IS NULL AND at.expires_at > CURRENT_TIMESTAMP
                     LIMIT 1`,
                    [crypto.createHash('sha256').update(token).digest('hex')]
                );
                if (rows.length) {
                    req.customerAccess = rows[0];
                }
            } catch (err) {
                // Abaikan kesalahan pencarian sesi opsional
            }
        }
        return next();
    }

    async function getServiceData(serviceId) {
        const [rows] = await db.execute(
            `SELECT s.id, s.no_nota, s.jenis_unit, s.merek_tipe, s.keluhan, s.kelengkapan, s.no_seri,
                    s.status, s.biaya_jasa, s.total_biaya, s.status_pembayaran, s.metode_pembayaran,
                    s.dp_dibayar, s.nominal_dibayar, s.kembalian, s.created_at,
                    s.teknisi_id, u.nama AS nama_teknisi, c.nama AS nama_pelanggan
             FROM services s
             JOIN customers c ON s.customer_id = c.id
             LEFT JOIN users u ON s.teknisi_id = u.id
             WHERE s.id = ? LIMIT 1`,
            [serviceId]
        );
        if (!rows.length) return null;
        const unit = rows[0];
        const [onderdil] = await db.execute(
            `SELECT sd.id, sd.sparepart_id, sd.jumlah, sd.harga_satuan, sd.subtotal,
                    sp.kode_barang, sp.nama_barang
             FROM service_details sd JOIN spareparts sp ON sd.sparepart_id = sp.id
             WHERE sd.service_id = ? ORDER BY sd.id ASC`,
            [serviceId]
        );
        const [timeline] = await db.execute(
            `SELECT status_lama, status_baru, catatan_teknis, created_at
             FROM service_logs WHERE service_id = ? ORDER BY created_at ASC`,
            [serviceId]
        );
        return { unit, onderdil, timeline };
    }

    router.post('/login', async (req, res) => {
        const accessCode = typeof req.body?.token === 'string' ? req.body.token.trim() : '';
        const customerName = typeof req.body?.customer_name === 'string'
            ? req.body.customer_name.trim().replace(/\s+/g, ' ')
            : '';
        const normalizedName = normalizeCustomerName(customerName);
        if (!/^(?:\d{5}|[A-Za-z0-9_-]{16})$/.test(accessCode) || !customerName || customerName.length > 100) {
            return res.status(401).json({ status: 'error', message: 'Nama pelanggan atau kode akses tidak cocok' });
        }

        const attemptKey = `${req.ip || req.socket.remoteAddress || 'unknown'}:${normalizedName}`;
        const now = Date.now();
        const attempts = customerLoginAttempts.get(attemptKey);
        if (attempts && attempts.resetAt <= now) customerLoginAttempts.delete(attemptKey);
        else if (attempts && attempts.count >= CUSTOMER_LOGIN_ATTEMPT_LIMIT) {
            res.setHeader('Retry-After', Math.ceil((attempts.resetAt - now) / 1000));
            return res.status(429).json({ status: 'error', message: 'Terlalu banyak percobaan. Coba lagi setelah 15 menit.' });
        }

        try {
            const [rows] = await db.execute(
                `SELECT at.id, at.service_id, c.nama AS nama_pelanggan
                 FROM service_access_tokens at
                 JOIN services s ON s.id = at.service_id
                 JOIN customers c ON c.id = s.customer_id
                 WHERE at.token_hash = ? AND at.revoked_at IS NULL AND at.expires_at > CURRENT_TIMESTAMP LIMIT 1`,
                [hashCustomerToken(accessCode)]
            );
            if (!rows.length || normalizeCustomerName(rows[0].nama_pelanggan) !== normalizedName) {
                recordFailedLogin(attemptKey, now);
                return res.status(401).json({ status: 'error', message: 'Nama pelanggan atau kode akses tidak cocok' });
            }
            const sessionToken = crypto.randomBytes(32).toString('hex');
            const expiresAt = new Date(Date.now() + SESSION_TTL_MS);
            const sessionHash = crypto.createHash('sha256').update(sessionToken).digest('hex');
            await db.execute('INSERT INTO customer_sessions (session_hash, access_token_id, expires_at) VALUES (?, ?, ?)', [sessionHash, rows[0].id, expiresAt]);
            customerLoginAttempts.delete(attemptKey);
            setCookie(res, req, sessionToken, Math.floor(SESSION_TTL_MS / 1000));
            return res.json({ status: 'success' });
        } catch (error) {
            console.error('Customer login failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Login pelanggan gagal diproses' });
        }
    });

    router.get('/me', requireCustomer, async (req, res) => {
        try {
            const data = await getServiceData(req.customerAccess.service_id);
            if (!data) return res.status(404).json({ status: 'error', message: 'Data servis tidak ditemukan' });
            return res.json({ status: 'success', data });
        } catch (error) {
            console.error('Customer tracking read failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Data servis gagal dimuat' });
        }
    });

    router.post(['/approval', '/persetujuan'], tryCustomerAuth, async (req, res) => {
        const rawStatus = typeof req.body?.status_baru === 'string' ? req.body.status_baru.toLowerCase().trim() : '';
        const status = (rawStatus === 'dikerjakan' || rawStatus === 'setuju') ? 'dikerjakan' : (rawStatus === 'batal' || rawStatus === 'batalkan') ? 'batal' : '';
        if (!['dikerjakan', 'batal'].includes(status)) {
            return res.status(400).json({ status: 'error', message: 'Pilihan persetujuan tidak valid' });
        }

        const serviceId = req.customerAccess?.service_id || req.body?.service_id;
        const noNota = req.body?.no_nota;

        if (!serviceId && !noNota) {
            return res.status(401).json({ status: 'error', message: 'Masukkan kode akses pelanggan atau nomor nota untuk melanjutkan' });
        }

        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();

            let query = 'SELECT id, status, total_biaya FROM services WHERE ';
            let params = [];
            if (serviceId) {
                query += 'id = ? FOR UPDATE';
                params.push(serviceId);
            } else {
                query += 'no_nota = ? FOR UPDATE';
                params.push(noNota);
            }

            const [rows] = await connection.execute(query, params);
            if (!rows.length) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Data servis tidak ditemukan' });
            }

            const service = rows[0];
            const currentStatusNorm = (service.status || '').toLowerCase().trim();
            if (currentStatusNorm !== 'menunggu_persetujuan' && currentStatusNorm !== 'menunggu persetujuan') {
                await connection.rollback();
                return res.status(409).json({ status: 'error', message: 'Servis tidak sedang menunggu persetujuan' });
            }

            await connection.execute('UPDATE services SET status = ? WHERE id = ?', [status, service.id]);
            const note = status === 'dikerjakan'
                ? `Pelanggan menyetujui estimasi perbaikan sebesar Rp ${Number(service.total_biaya || 0).toLocaleString('id-ID')}.`
                : 'Pelanggan membatalkan servis melalui portal pelanggan.';
            await connection.execute(
                'INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) VALUES (?, ?, ?, ?)',
                [service.id, service.status, status, note]
            );
            await connection.commit();
            return res.json({ status: 'success', message: 'Persetujuan pelanggan tersimpan' });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Customer approval failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Persetujuan gagal disimpan' });
        } finally {
            if (connection) connection.release();
        }
    });

    router.get('/:no_nota', async (req, res, next) => {
        const { no_nota } = req.params;
        if (no_nota === 'me' || no_nota === 'login' || no_nota === 'logout' || no_nota === 'approval' || no_nota === 'persetujuan') {
            return next();
        }
        try {
            const [rows] = await db.execute('SELECT id FROM services WHERE no_nota = ? LIMIT 1', [no_nota]);
            if (!rows.length) {
                return res.status(404).json({ status: 'error', message: 'Nomor nota tidak ditemukan' });
            }
            const data = await getServiceData(rows[0].id);
            if (!data) return res.status(404).json({ status: 'error', message: 'Data servis tidak ditemukan' });
            return res.json({ status: 'success', data });
        } catch (error) {
            console.error('Customer tracking by nota failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Data servis gagal dimuat' });
        }
    });

    router.post('/logout', async (req, res) => {
        const rawToken = readCookie(req);
        try {
            if (/^[a-f0-9]{64}$/i.test(rawToken)) {
                const sessionHash = crypto.createHash('sha256').update(rawToken).digest('hex');
                await db.execute('DELETE FROM customer_sessions WHERE session_hash = ?', [sessionHash]);
            }
            clearCookie(res, req);
            return res.json({ status: 'success' });
        } catch (error) {
            clearCookie(res, req);
            return res.status(500).json({ status: 'error', message: 'Logout gagal diproses' });
        }
    });

    return router;
}

module.exports = { createCustomerRouter };
