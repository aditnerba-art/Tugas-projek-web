const express = require('express');
const router = express.Router();
const db = require('../config/db');

// GET /api/tracking/:no_nota (Data Pelacakan Servis Transparan untuk Pelanggan)
router.get('/:no_nota', async (req, res) => {
    try {
        const { no_nota } = req.params;
        const [services] = await db.execute(`
            SELECT 
                s.id, s.no_nota, s.jenis_unit, s.merek_tipe, s.keluhan, s.kelengkapan, s.no_seri,
                s.status, s.biaya_jasa, s.total_biaya, s.status_pembayaran, s.metode_pembayaran,
                s.dp_dibayar, s.nominal_dibayar, s.kembalian, s.created_at,
                s.teknisi_id,
                u.nama AS nama_teknisi,
                c.nama AS nama_pelanggan
            FROM services s
            JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.teknisi_id = u.id
            WHERE s.no_nota = ?
        `, [no_nota]);

        if (services.length === 0) {
            return res.status(404).json({ status: 'error', message: 'Nomor nota tidak ditemukan' });
        }

        const serviceData = services[0];

        // 1. Ambil Rincian Onderdil / Sparepart yang Digunakan
        const [sparepartDetails] = await db.execute(`
            SELECT 
                sd.id, sd.sparepart_id, sd.jumlah, sd.harga_satuan, sd.subtotal,
                sp.kode_barang, sp.nama_barang
            FROM service_details sd
            JOIN spareparts sp ON sd.sparepart_id = sp.id
            WHERE sd.service_id = ?
            ORDER BY sd.id ASC
        `, [serviceData.id]);

        // 2. Ambil Riwayat Timeline Pengerjaan dari service_logs
        const [logs] = await db.execute(`
            SELECT status_lama, status_baru, catatan_teknis, created_at 
            FROM service_logs 
            WHERE service_id = ?
            ORDER BY created_at ASC
        `, [serviceData.id]);

        res.json({
            status: 'success',
            data: {
                unit: serviceData,
                onderdil: sparepartDetails,
                timeline: logs
            }
        });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// POST /api/tracking/persetujuan (Konfirmasi Persetujuan Pelanggan: DIKERJAKAN / BATAL)
router.post('/persetujuan', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { service_id, no_nota, status_baru, catatan } = req.body;

        if (!service_id && !no_nota) {
            await connection.rollback();
            return res.status(400).json({ status: 'error', message: 'ID Servis atau No Nota wajib disertakan' });
        }

        // Ambil data servis saat ini
        let query = `SELECT id, no_nota, status, total_biaya FROM services WHERE `;
        let param = [];
        if (service_id) {
            query += `id = ? FOR UPDATE`;
            param.push(service_id);
        } else {
            query += `no_nota = ? FOR UPDATE`;
            param.push(no_nota);
        }

        const [srv] = await connection.execute(query, param);
        if (srv.length === 0) {
            await connection.rollback();
            return res.status(404).json({ status: 'error', message: 'Servis tidak ditemukan' });
        }

        const currentService = srv[0];
        const status_lama = currentService.status;
        const totalBiaya = parseFloat(currentService.total_biaya || 0);

        // Normalisasi status baru (dikerjakan / batal)
        const normalized = (status_baru || '').toString().toLowerCase().trim();
        let targetStatus = 'dikerjakan';
        let defaultCatatan = '';

        if (normalized === 'batal' || normalized === 'batalkan') {
            targetStatus = 'batal';
            defaultCatatan = `Pelanggan MEMBATALKAN servis perbaikan setelah melihat estimasi biaya (Rp ${totalBiaya.toLocaleString('id-ID')}).`;
        } else {
            targetStatus = 'dikerjakan';
            defaultCatatan = `Pelanggan MENYETUJUI estimasi perbaikan sebesar Rp ${totalBiaya.toLocaleString('id-ID')}. Perbaikan unit segera dikerjakan.`;
        }

        const finalCatatan = catatan || defaultCatatan;

        // Update status di tabel services
        await connection.execute(
            `UPDATE services SET status = ? WHERE id = ?`,
            [targetStatus, currentService.id]
        );

        // Catat ke service_logs
        await connection.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) VALUES (?, ?, ?, ?)`,
            [currentService.id, status_lama, targetStatus, finalCatatan]
        );

        await connection.commit();

        res.json({
            status: 'success',
            message: targetStatus === 'dikerjakan'
                ? 'Persetujuan perbaikan berhasil dikonfirmasi'
                : 'Pembatalan servis berhasil dikonfirmasi',
            data: {
                service_id: currentService.id,
                status_lama,
                status_baru: targetStatus
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