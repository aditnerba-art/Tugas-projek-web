const express = require('express');
const router = express.Router();
const db = require('../config/db');

// 1. GET /api/owner/dashboard
router.get('/dashboard', async (req, res) => {
    try {
        // Ringkasan Total Pendapatan & Jumlah Unit Servis
        const [summary] = await db.execute(`
            SELECT 
                COUNT(id) AS total_unit,
                SUM(CASE WHEN status = 'selesai' OR status = 'diambil' THEN 1 ELSE 0 END) AS unit_selesai,
                SUM(CASE WHEN status_pembayaran = 'lunas' THEN total_biaya ELSE 0 END) AS total_pendapatan,
                SUM(CASE WHEN status_pembayaran = 'dp' THEN dp_dibayar ELSE 0 END) AS total_dp
            FROM services
        `);

        // Unit Berdasarkan Status Servis
        const [statusCounts] = await db.execute(`
            SELECT status, COUNT(id) AS jumlah 
            FROM services 
            GROUP BY status
        `);

        // Ringkasan Pengadaan Barang (Purchases)
        const [purchaseSummary] = await db.execute(`
            SELECT 
                COUNT(id) AS total_transaksi_pengadaan,
                COALESCE(SUM(total_biaya), 0) AS total_pengeluaran_pengadaan
            FROM purchases
        `);

        // Ringkasan Inventaris Sparepart
        const [sparepartSummary] = await db.execute(`
            SELECT 
                COUNT(id) AS total_jenis_barang,
                COALESCE(SUM(stok), 0) AS total_stok_tersedia,
                COALESCE(SUM(stok * harga_beli), 0) AS total_nilai_aset_barang
            FROM spareparts
        `);

        res.json({
            status: 'success',
            data: {
                summary: summary[0],
                statusCounts,
                purchases: purchaseSummary[0],
                spareparts: sparepartSummary[0]
            }
        });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 2. GET /api/owner/purchases (Riwayat Pengadaan Barang Lengkap)
router.get('/purchases', async (req, res) => {
    try {
        const [purchases] = await db.execute(`
            SELECT p.id, p.no_pembelian, p.supplier, p.tanggal_pembelian, p.total_biaya, p.keterangan, p.created_at
            FROM purchases p
            ORDER BY p.tanggal_pembelian DESC, p.id DESC
        `);

        // Ambil detail items untuk setiap purchase
        for (const p of purchases) {
            const [details] = await db.execute(`
                SELECT pd.id, pd.sparepart_id, pd.jumlah, pd.harga_beli_satuan, pd.subtotal,
                       sp.kode_barang, sp.nama_barang, sp.stok AS stok_sekarang
                FROM purchase_details pd
                JOIN spareparts sp ON pd.sparepart_id = sp.id
                WHERE pd.purchase_id = ?
            `, [p.id]);
            p.items = details;
        }

        res.json({ status: 'success', data: purchases });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 3. POST /api/owner/purchases (Simpan Pengadaan Barang Baru & Otomatis Tambah Stok Spareparts)
router.post('/purchases', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { supplier, tanggal_pembelian, keterangan, items } = req.body;

        if (!supplier || !tanggal_pembelian || !Array.isArray(items) || items.length === 0) {
            await connection.rollback();
            return res.status(400).json({
                status: 'error',
                message: 'Supplier, tanggal pembelian, dan minimal 1 item barang harus disertakan'
            });
        }

        // Generate No. Pembelian / Purchase Order PO-YYYYMMDD-XXXX
        const todayStr = (tanggal_pembelian || new Date().toISOString().slice(0, 10)).replace(/-/g, '');
        const no_pembelian = `PO-${todayStr}-${Math.floor(1000 + Math.random() * 9000)}`;

        let total_biaya = 0;
        const processedItems = [];

        for (const item of items) {
            let sparepartId = item.sparepart_id;
            const jumlah = parseInt(item.jumlah, 10);
            const hargaBeliSatuan = parseFloat(item.harga_beli_satuan);

            if (isNaN(jumlah) || jumlah <= 0 || isNaN(hargaBeliSatuan) || hargaBeliSatuan < 0) {
                await connection.rollback();
                return res.status(400).json({
                    status: 'error',
                    message: 'Jumlah dan harga beli satuan setiap item harus valid'
                });
            }

            // Jika merupakan sparepart baru yang didaftarkan sekalian saat pengadaan
            if (item.is_new) {
                if (!item.nama_barang || !item.kode_barang) {
                    await connection.rollback();
                    return res.status(400).json({
                        status: 'error',
                        message: 'Sparepart baru wajib menyertakan kode dan nama barang'
                    });
                }

                // Cek apakah kode barang sudah ada
                const [existing] = await connection.execute(
                    `SELECT id FROM spareparts WHERE kode_barang = ?`,
                    [item.kode_barang]
                );

                if (existing.length > 0) {
                    sparepartId = existing[0].id;
                } else {
                    const hargaJual = parseFloat(item.harga_jual) || (hargaBeliSatuan * 1.3); // default markup 30%
                    const [newSp] = await connection.execute(
                        `INSERT INTO spareparts (kode_barang, nama_barang, stok, harga_beli, harga_jual) VALUES (?, ?, 0, ?, ?)`,
                        [item.kode_barang, item.nama_barang, hargaBeliSatuan, hargaJual]
                    );
                    sparepartId = newSp.insertId;
                }
            }

            if (!sparepartId) {
                await connection.rollback();
                return res.status(400).json({ status: 'error', message: 'ID Sparepart tidak valid' });
            }

            // Verifikasi sparepart ada
            const [spRows] = await connection.execute(
                `SELECT id, nama_barang, stok FROM spareparts WHERE id = ? FOR UPDATE`,
                [sparepartId]
            );

            if (spRows.length === 0) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: `Sparepart ID ${sparepartId} tidak ditemukan` });
            }

            const subtotal = jumlah * hargaBeliSatuan;
            total_biaya += subtotal;

            processedItems.push({
                sparepart_id: sparepartId,
                nama_barang: spRows[0].nama_barang,
                jumlah,
                harga_beli_satuan: hargaBeliSatuan,
                subtotal
            });
        }

        // 1. Simpan ke tabel purchases
        const [purchaseResult] = await connection.execute(
            `INSERT INTO purchases (no_pembelian, supplier, tanggal_pembelian, total_biaya, keterangan) VALUES (?, ?, ?, ?, ?)`,
            [no_pembelian, supplier, tanggal_pembelian, total_biaya, keterangan || null]
        );
        const purchaseId = purchaseResult.insertId;

        // 2. Simpan setiap item ke purchase_details dan OTOMATIS TAMBAH STOK di spareparts
        for (const item of processedItems) {
            await connection.execute(
                `INSERT INTO purchase_details (purchase_id, sparepart_id, jumlah, harga_beli_satuan, subtotal) VALUES (?, ?, ?, ?, ?)`,
                [purchaseId, item.sparepart_id, item.jumlah, item.harga_beli_satuan, item.subtotal]
            );

            // Update stok dan update harga beli terbaru di tabel spareparts
            await connection.execute(
                `UPDATE spareparts SET stok = stok + ?, harga_beli = ? WHERE id = ?`,
                [item.jumlah, item.harga_beli_satuan, item.sparepart_id]
            );
        }

        await connection.commit();

        res.json({
            status: 'success',
            message: `Pengadaan barang berhasil disimpan. Stok sparepart otomatis bertambah!`,
            data: {
                purchase_id: purchaseId,
                no_pembelian,
                supplier,
                total_biaya,
                total_items: processedItems.length
            }
        });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ status: 'error', message: error.message });
    } finally {
        connection.release();
    }
});

module.exports = router;