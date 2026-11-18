const express = require('express');
const { generateCustomerToken, hashCustomerToken } = require('../lib/customer-token');
const { deleteServiceRecords } = require('../lib/service-deletion');

function createAdminCustomersRouter(db) {
    const router = express.Router();

    router.get('/access-codes', async (req, res) => {
        try {
            const [rows] = await db.execute(`
                SELECT s.id AS service_id, s.no_nota, c.nama AS nama_pelanggan,
                       s.jenis_unit, s.merek_tipe, s.status,
                       EXISTS (
                           SELECT 1 FROM service_access_tokens at
                           WHERE at.service_id = s.id
                             AND at.revoked_at IS NULL
                             AND at.expires_at > CURRENT_TIMESTAMP
                       ) AS has_active_code
                FROM services s
                JOIN customers c ON c.id = s.customer_id
                WHERE c.nama <> 'Pelanggan dihapus'
                  AND COALESCE(c.no_wa, '') NOT LIKE 'DIHAPUS-%'
                ORDER BY s.created_at DESC, s.id DESC
            `);
            return res.json({ status: 'success', data: rows });
        } catch (error) {
            console.error('Failed to load admin access codes:', error.message);
            return res.status(500).json({ status: 'error', message: 'Daftar kode akses gagal dimuat' });
        }
    });

    router.post('/access-codes/:serviceId', async (req, res) => {
        if (!/^[1-9]\d*$/.test(req.params.serviceId) || !Number.isSafeInteger(Number(req.params.serviceId))) {
            return res.status(400).json({ status: 'error', message: 'ID servis tidak valid' });
        }

        const serviceId = Number(req.params.serviceId);
        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();
            const [services] = await connection.execute(
                'SELECT id FROM services WHERE id = ? FOR UPDATE',
                [serviceId]
            );
            if (!services.length) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Servis tidak ditemukan' });
            }

            // Old codes are one-way hashed, so rotate them rather than attempting to recover them.
            await connection.execute(
                `DELETE cs FROM customer_sessions cs
                 JOIN service_access_tokens at ON at.id = cs.access_token_id
                 WHERE at.service_id = ?`,
                [serviceId]
            );
            await connection.execute(
                `UPDATE service_access_tokens
                 SET revoked_at = CURRENT_TIMESTAMP
                 WHERE service_id = ? AND revoked_at IS NULL`,
                [serviceId]
            );

            let customerToken;
            let tokenInserted = false;
            const expiresAt = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000);
            for (let attempt = 0; attempt < 20; attempt++) {
                customerToken = generateCustomerToken();
                try {
                    await connection.execute(
                        'INSERT INTO service_access_tokens (service_id, token_hash, expires_at) VALUES (?, ?, ?)',
                        [serviceId, hashCustomerToken(customerToken), expiresAt]
                    );
                    tokenInserted = true;
                    break;
                } catch (error) {
                    if (error.code !== 'ER_DUP_ENTRY') throw error;
                }
            }
            if (!tokenInserted) throw new Error('Gagal membuat kode akses unik. Silakan coba lagi.');

            await connection.commit();
            return res.json({
                status: 'success',
                message: 'Kode baru berhasil dibuat. Kode sebelumnya sudah tidak berlaku.',
                data: { service_id: serviceId, customer_token: customerToken }
            });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Failed to rotate customer access code:', error.message);
            return res.status(500).json({ status: 'error', message: 'Kode akses gagal dibuat' });
        } finally {
            if (connection) connection.release();
        }
    });

    router.get('/', async (req, res) => {
        try {
            const [rows] = await db.execute(`
                SELECT c.id, c.nama, c.no_wa, c.alamat, c.created_at, COUNT(s.id) AS jumlah_servis
                FROM customers c
                LEFT JOIN services s ON s.customer_id = c.id
                WHERE c.nama <> 'Pelanggan dihapus'
                  AND COALESCE(c.no_wa, '') NOT LIKE 'DIHAPUS-%'
                GROUP BY c.id, c.nama, c.no_wa, c.alamat, c.created_at
                ORDER BY c.nama ASC, c.id ASC
            `);
            return res.json({ status: 'success', data: rows });
        } catch (error) {
            console.error('Failed to load admin customers:', error.message);
            return res.status(500).json({ status: 'error', message: 'Daftar pelanggan gagal dimuat' });
        }
    });

    router.put('/:id', async (req, res) => {
        if (!/^[1-9]\d*$/.test(req.params.id)) {
            return res.status(400).json({ status: 'error', message: 'ID pelanggan tidak valid' });
        }
        const customerId = Number(req.params.id);
        const nama = typeof req.body?.nama === 'string' ? req.body.nama.trim().replace(/\s+/g, ' ') : '';
        const noWa = typeof req.body?.no_wa === 'string' ? req.body.no_wa.trim() : '';
        const alamat = typeof req.body?.alamat === 'string' ? req.body.alamat.trim() : '';
        if (!Number.isSafeInteger(customerId) || !nama || nama.length > 100 || !noWa || noWa.length > 20 || alamat.length > 10000) {
            return res.status(400).json({ status: 'error', message: 'Nama, WhatsApp, atau alamat tidak valid' });
        }

        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();
            const [customers] = await connection.execute(
                'SELECT id, nama, no_wa FROM customers WHERE id = ? FOR UPDATE',
                [customerId]
            );
            if (!customers.length) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Pelanggan tidak ditemukan' });
            }
            if (customers[0].nama === 'Pelanggan dihapus' || String(customers[0].no_wa).startsWith('DIHAPUS-')) {
                await connection.rollback();
                return res.status(409).json({ status: 'error', message: 'Identitas sudah dihapus dan tidak dapat dipulihkan' });
            }

            await connection.execute(
                'UPDATE customers SET nama = ?, no_wa = ?, alamat = ? WHERE id = ?',
                [nama, noWa, alamat || null, customerId]
            );
            await connection.commit();
            return res.json({ status: 'success', message: 'Data pelanggan berhasil diperbarui' });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Failed to update admin customer:', error.message);
            return res.status(500).json({ status: 'error', message: 'Data pelanggan gagal diperbarui' });
        } finally {
            if (connection) connection.release();
        }
    });

    router.delete('/:id', async (req, res) => {
        if (!/^[1-9]\d*$/.test(req.params.id)) {
            return res.status(400).json({ status: 'error', message: 'ID pelanggan tidak valid' });
        }
        const customerId = Number(req.params.id);
        if (!Number.isSafeInteger(customerId)) {
            return res.status(400).json({ status: 'error', message: 'ID pelanggan tidak valid' });
        }

        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();
            const [customers] = await connection.execute(
                'SELECT id FROM customers WHERE id = ? FOR UPDATE',
                [customerId]
            );
            if (!customers.length) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Pelanggan tidak ditemukan' });
            }

            const [services] = await connection.execute(
                'SELECT id FROM services WHERE customer_id = ? FOR UPDATE',
                [customerId]
            );
            await deleteServiceRecords(connection, services.map(service => service.id));
            const [result] = await connection.execute(
                'DELETE FROM customers WHERE id = ?',
                [customerId]
            );
            if (!result.affectedRows) {
                await connection.rollback();
                return res.status(404).json({ success: false, message: 'Pelanggan tidak ditemukan' });
            }

            await connection.commit();
            return res.json({ success: true, message: 'Data pelanggan berhasil dihapus' });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Failed to delete admin customer:', error.message);
            return res.status(500).json({ success: false, message: 'Data pelanggan gagal dihapus' });
        } finally {
            if (connection) connection.release();
        }
    });

    return router;
}

module.exports = { createAdminCustomersRouter };
