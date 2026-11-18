const express = require('express');
const router = express.Router();
const db = require('../config/db');

// 1. GET /api/spareparts (Ambil Semua Sparepart dengan fitur pencarian)
router.get('/', async (req, res) => {
    try {
        const { search } = req.query;
        let query = `SELECT * FROM spareparts`;
        const params = [];

        if (search) {
            query += ` WHERE kode_barang LIKE ? OR nama_barang LIKE ?`;
            params.push(`%${search}%`, `%${search}%`);
        }

        query += ` ORDER BY nama_barang ASC`;
        const [rows] = await db.execute(query, params);
        res.json({ status: 'success', data: rows });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 2. GET /api/spareparts/:id (Ambil Detail 1 Sparepart)
router.get('/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [rows] = await db.execute(`SELECT * FROM spareparts WHERE id = ?`, [id]);
        if (rows.length === 0) {
            return res.status(404).json({ status: 'error', message: 'Sparepart tidak ditemukan' });
        }
        res.json({ status: 'success', data: rows[0] });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 3. POST /api/spareparts (Tambah Sparepart Baru - Create)
router.post('/', async (req, res) => {
    try {
        const { kode_barang, nama_barang, stok, harga_beli, harga_jual } = req.body;

        if (!kode_barang || !nama_barang) {
            return res.status(400).json({ status: 'error', message: 'Kode dan nama barang wajib diisi' });
        }

        // Cek duplikasi kode barang
        const [existing] = await db.execute(`SELECT id FROM spareparts WHERE kode_barang = ?`, [kode_barang]);
        if (existing.length > 0) {
            return res.status(400).json({ status: 'error', message: `Kode barang "${kode_barang}" sudah digunakan` });
        }

        const [result] = await db.execute(
            `INSERT INTO spareparts (kode_barang, nama_barang, stok, harga_beli, harga_jual) VALUES (?, ?, ?, ?, ?)`,
            [kode_barang, nama_barang, parseInt(stok, 10) || 0, parseFloat(harga_beli) || 0, parseFloat(harga_jual) || 0]
        );

        res.json({
            status: 'success',
            message: 'Sparepart baru berhasil ditambahkan',
            data: { id: result.insertId, kode_barang, nama_barang }
        });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 4. PUT /api/spareparts/:id (Update / Edit Sparepart)
router.put('/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { kode_barang, nama_barang, stok, harga_beli, harga_jual } = req.body;

        const [check] = await db.execute(`SELECT id FROM spareparts WHERE id = ?`, [id]);
        if (check.length === 0) {
            return res.status(404).json({ status: 'error', message: 'Sparepart tidak ditemukan' });
        }

        // Cek jika kode barang bentrok dengan id lain
        if (kode_barang) {
            const [conflict] = await db.execute(
                `SELECT id FROM spareparts WHERE kode_barang = ? AND id != ?`,
                [kode_barang, id]
            );
            if (conflict.length > 0) {
                return res.status(400).json({ status: 'error', message: `Kode barang "${kode_barang}" sudah digunakan item lain` });
            }
        }

        await db.execute(
            `UPDATE spareparts SET kode_barang = ?, nama_barang = ?, stok = ?, harga_beli = ?, harga_jual = ? WHERE id = ?`,
            [
                kode_barang,
                nama_barang,
                parseInt(stok, 10) || 0,
                parseFloat(harga_beli) || 0,
                parseFloat(harga_jual) || 0,
                id
            ]
        );

        res.json({ status: 'success', message: 'Data sparepart berhasil diperbarui' });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 5. DELETE /api/spareparts/:id (Hapus Sparepart)
router.delete('/:id', async (req, res) => {
    try {
        const { id } = req.params;

        const [check] = await db.execute(`SELECT id, nama_barang FROM spareparts WHERE id = ?`, [id]);
        if (check.length === 0) {
            return res.status(404).json({ status: 'error', message: 'Sparepart tidak ditemukan' });
        }

        // Cek apakah sparepart pernah digunakan di service_details
        const [servCheck] = await db.execute(`SELECT id FROM service_details WHERE sparepart_id = ? LIMIT 1`, [id]);
        if (servCheck.length > 0) {
            return res.status(400).json({
                status: 'error',
                message: `Sparepart "${check[0].nama_barang}" tidak dapat dihapus karena tercatat dalam riwayat penggunaan servis!`
            });
        }

        // Cek apakah sparepart pernah digunakan di purchase_details
        const [purchCheck] = await db.execute(`SELECT id FROM purchase_details WHERE sparepart_id = ? LIMIT 1`, [id]);
        if (purchCheck.length > 0) {
            return res.status(400).json({
                status: 'error',
                message: `Sparepart "${check[0].nama_barang}" tidak dapat dihapus karena tercatat dalam riwayat pengadaan barang!`
            });
        }

        await db.execute(`DELETE FROM spareparts WHERE id = ?`, [id]);
        res.json({ status: 'success', message: `Sparepart "${check[0].nama_barang}" berhasil dihapus` });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

module.exports = router;