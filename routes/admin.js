const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { generateNotaNumber } = require('../lib/nota-number');
const { technicianScope } = require('../lib/technician-scope');
const { generateCustomerToken, hashCustomerToken, customerPortalUrl } = require('../lib/customer-token');
const { createAdminServiceDeleteRouter } = require('./admin-service-delete');

router.use(createAdminServiceDeleteRouter(db));

// 0. GET /api/admin/teknisi (Ambil Daftar Teknisi untuk Assignment Unit)
router.get('/teknisi', async (req, res) => {
    try {
        const [rows] = await db.execute(
            `SELECT id, nama, username, role FROM users WHERE role = 'teknisi' ORDER BY nama ASC`
        );
        res.json({ status: 'success', data: rows });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// GET /api/admin/users (Ambil Semua Data User & Role)
router.get('/users', async (req, res) => {
    try {
        const [rows] = await db.execute(
            `SELECT id, nama, username, role, created_at FROM users ORDER BY role ASC, nama ASC`
        );
        res.json({ status: 'success', data: rows });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 1. POST /api/admin/service (Pendaftaran Unit Masuk & Assignment Teknisi)
router.post('/service', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { nama, no_wa, alamat, jenis_unit, merek_tipe, no_seri, keluhan, kelengkapan, teknisi_id } = req.body;

        if (!nama || !no_wa || !merek_tipe || !keluhan) {
            await connection.rollback();
            return res.status(400).json({ status: 'error', message: 'Data wajib belum lengkap' });
        }

        const [custResult] = await connection.execute(
            `INSERT INTO customers (nama, no_wa, alamat) VALUES (?, ?, ?)`,
            [nama, no_wa, alamat || null]
        );
        const customerId = custResult.insertId;

        const no_nota = generateNotaNumber();

        const assignedTeknisiId = teknisi_id ? parseInt(teknisi_id, 10) : null;

        const [srvResult] = await connection.execute(
            `INSERT INTO services (no_nota, customer_id, teknisi_id, jenis_unit, merek_tipe, no_seri, keluhan, kelengkapan, status, status_pembayaran) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'masuk', 'belum_bayar')`,
            [no_nota, customerId, assignedTeknisiId, jenis_unit, merek_tipe, no_seri || null, keluhan, kelengkapan || null]
        );
        const serviceId = srvResult.insertId;
        const customerTokenExpiresAt = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000);
        let customerAccessToken;
        let accessTokenInserted = false;
        for (let attempt = 0; attempt < 20; attempt++) {
            customerAccessToken = generateCustomerToken();
            try {
                await connection.execute(
                    'INSERT INTO service_access_tokens (service_id, token_hash, expires_at) VALUES (?, ?, ?)',
                    [serviceId, hashCustomerToken(customerAccessToken), customerTokenExpiresAt]
                );
                accessTokenInserted = true;
                break;
            } catch (error) {
                if (error.code !== 'ER_DUP_ENTRY') throw error;
            }
        }
        if (!accessTokenInserted) throw new Error('Gagal membuat kode akses unik. Silakan coba lagi.');
        const customerLink = customerPortalUrl(process.env.PUBLIC_BASE_URL || '');
        const customerMessage = customerLink
            ? `Halo ${nama}, perangkat Anda sudah diterima untuk servis. Pantau statusnya di ${customerLink}. Kode akses pelanggan Anda: ${customerAccessToken}`
            : null;

        // Ambil nama teknisi jika dipilih untuk dicatat di log
        let logCatatan = 'Unit berhasil didaftarkan di sistem';
        if (assignedTeknisiId) {
            const [tekRows] = await connection.execute(`SELECT nama FROM users WHERE id = ?`, [assignedTeknisiId]);
            if (tekRows.length > 0) {
                logCatatan += ` dan ditugaskan kepada teknisi: ${tekRows[0].nama}`;
            }
        }

        await connection.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) 
             VALUES (?, NULL, 'masuk', ?)`,
            [serviceId, logCatatan]
        );

        await connection.commit();
        res.json({
            status: 'success',
            message: 'Unit berhasil didaftarkan',
            no_nota,
            service_id: serviceId,
            customer_token: customerAccessToken,
            customer_link: customerLink,
            customer_message: customerMessage,
            whatsapp_sent: false,
            whatsapp_status: customerLink ? 'provider_not_configured' : 'public_url_missing'
        });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ status: 'error', message: error.message });
    } finally {
        connection.release();
    }
});

// PUT /api/admin/service/assign-teknisi (Ubah Assignment Teknisi Penanggung Jawab)
router.put('/service/assign-teknisi', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { service_id, teknisi_id } = req.body;
        if (!service_id) {
            await connection.rollback();
            return res.status(400).json({ status: 'error', message: 'ID servis wajib disertakan' });
        }

        let teknisiNama = 'Belum Ditugaskan';
        const assignedId = teknisi_id ? parseInt(teknisi_id, 10) : null;

        if (assignedId) {
            const [tekRows] = await connection.execute(`SELECT nama FROM users WHERE id = ? AND role = 'teknisi'`, [assignedId]);
            if (tekRows.length === 0) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Teknisi tidak ditemukan' });
            }
            teknisiNama = tekRows[0].nama;
        }

        const [srvRows] = await connection.execute(`SELECT status FROM services WHERE id = ?`, [service_id]);
        if (srvRows.length === 0) {
            await connection.rollback();
            return res.status(404).json({ status: 'error', message: 'Servis tidak ditemukan' });
        }

        await connection.execute(
            `UPDATE services SET teknisi_id = ? WHERE id = ?`,
            [assignedId, service_id]
        );

        await connection.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) 
             VALUES (?, ?, ?, ?)`,
            [service_id, srvRows[0].status, srvRows[0].status, `Penugasan Teknisi PIC diperbarui: ${teknisiNama}`]
        );

        await connection.commit();
        res.json({ status: 'success', message: `Teknisi penanggung jawab berhasil ditugaskan: ${teknisiNama}` });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ status: 'error', message: error.message });
    } finally {
        connection.release();
    }
});

// 2. PUT /api/admin/service/status (Update Status & Catatan & Potong Stok Sparepart)
router.put('/service/status', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { service_id, status_baru, catatan_teknis, biaya_jasa, total_biaya, sparepart_id, jumlah_sparepart } = req.body;

        const assignment = technicianScope(req.authUser);
        const [srv] = await connection.execute(
            `SELECT status, biaya_jasa, total_biaya FROM services WHERE id = ?${assignment.clause} FOR UPDATE`,
            [service_id, ...assignment.params]
        );
        if (srv.length === 0) {
            await connection.rollback();
            return res.status(404).json({ status: 'error', message: 'Servis tidak ditemukan' });
        }

        const status_lama = srv[0].status;
        let subtotalSparepart = 0;

        // Pemotongan stok sparepart jika teknisi menginput sparepart_id
        if (sparepart_id) {
            const qty = parseInt(jumlah_sparepart, 10);
            if (isNaN(qty) || qty <= 0) {
                await connection.rollback();
                return res.status(400).json({ status: 'error', message: 'Jumlah sparepart harus lebih dari 0' });
            }

            const [spRows] = await connection.execute(
                `SELECT id, nama_barang, stok, harga_jual FROM spareparts WHERE id = ? FOR UPDATE`,
                [sparepart_id]
            );

            if (spRows.length === 0) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Sparepart tidak ditemukan' });
            }

            const sparepart = spRows[0];
            if (sparepart.stok < qty) {
                await connection.rollback();
                return res.status(400).json({
                    status: 'error',
                    message: `Stok sparepart "${sparepart.nama_barang}" tidak mencukupi (sisa: ${sparepart.stok})`
                });
            }

            // Potong stok di tabel spareparts
            await connection.execute(
                `UPDATE spareparts SET stok = stok - ? WHERE id = ?`,
                [qty, sparepart_id]
            );

            // Simpan detail penggunaan sparepart ke tabel service_details
            const harga_satuan = parseFloat(sparepart.harga_jual) || 0;
            subtotalSparepart = harga_satuan * qty;
            await connection.execute(
                `INSERT INTO service_details (service_id, sparepart_id, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)`,
                [service_id, sparepart_id, qty, harga_satuan, subtotalSparepart]
            );
        }

        // Hitung total akumulasi biaya
        const currentBiayaJasa = (biaya_jasa !== undefined && biaya_jasa !== null && biaya_jasa !== '') 
            ? parseFloat(biaya_jasa) 
            : parseFloat(srv[0].biaya_jasa || 0);

        // Hitung total dari seluruh spareparts yang terpasang di service_details
        const [sumDetails] = await connection.execute(
            `SELECT COALESCE(SUM(subtotal), 0) AS total_sparepart FROM service_details WHERE service_id = ?`,
            [service_id]
        );
        const totalSparepart = parseFloat(sumDetails[0].total_sparepart || 0);

        let finalTotal = currentBiayaJasa + totalSparepart;
        if (total_biaya !== undefined && total_biaya !== null && total_biaya !== '' && parseFloat(total_biaya) > 0) {
            // Jika teknisi secara eksplisit mengeset total biaya manual
            finalTotal = parseFloat(total_biaya);
        }

        await connection.execute(
            `UPDATE services SET status = ?, biaya_jasa = ?, total_biaya = ? WHERE id = ?`,
            [status_baru, currentBiayaJasa, finalTotal, service_id]
        );

        await connection.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) VALUES (?, ?, ?, ?)`,
            [service_id, status_lama, status_baru, catatan_teknis || null]
        );

        await connection.commit();
        res.json({
            status: 'success',
            message: 'Status servis berhasil diperbarui',
            biaya_jasa: currentBiayaJasa,
            total_biaya: finalTotal
        });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ status: 'error', message: error.message });
    } finally {
        connection.release();
    }
});

// 2b. POST /api/admin/service/persetujuan (Konfirmasi Persetujuan Pelanggan: DIKERJAKAN / BATAL)
router.post('/service/persetujuan', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const { service_id, no_nota, status_baru, catatan } = req.body;

        if (!service_id && !no_nota) {
            await connection.rollback();
            return res.status(400).json({ status: 'error', message: 'ID Servis atau No Nota wajib disertakan' });
        }

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

        await connection.execute(
            `UPDATE services SET status = ? WHERE id = ?`,
            [targetStatus, currentService.id]
        );

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

// 3. GET /api/admin/services-active (Daftar Unit Aktif & Kasir dengan Info Teknisi & Pembayaran)
router.get('/services-active', async (req, res) => {
    try {
        const assignment = technicianScope(req.authUser, 's');
        const legacyCustomerFilter = req.authUser && req.authUser.role === 'admin'
            ? " AND c.nama <> 'Pelanggan dihapus' AND COALESCE(c.no_wa, '') NOT LIKE 'DIHAPUS-%'"
            : '';
        const [rows] = await db.execute(`
            SELECT 
                s.id, s.no_nota, s.jenis_unit, s.merek_tipe, s.status, 
                s.biaya_jasa, s.total_biaya, s.status_pembayaran, s.metode_pembayaran,
                s.dp_dibayar, s.nominal_dibayar, s.kembalian, s.created_at,
                s.teknisi_id,
                u.nama AS nama_teknisi,
                c.nama AS nama_pelanggan, c.no_wa 
            FROM services s
            JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.teknisi_id = u.id
            WHERE 1 = 1${legacyCustomerFilter}${assignment.clause}
            ORDER BY s.created_at DESC
        `, assignment.params);
        res.json({ status: 'success', data: rows });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 4. PUT /api/admin/service/pembayaran (Kelola Pembayaran: Status, Metode, Nominal, Kembalian)
router.put('/service/pembayaran', async (req, res) => {
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        const {
            service_id,
            status_pembayaran,     // 'belum_bayar' | 'dp' | 'lunas'
            metode_pembayaran,     // 'Cash' | 'QRIS' | 'Transfer'
            nominal_dibayar,       // Uang diterima / cash tendered
            dp_dibayar,            // Jika pembayaran DP
            kembalian,             // Uang kembalian
            catatan,
            ambil_unit             // boolean: tandai unit telah diserahkan / diambil
        } = req.body;

        if (!service_id) {
            await connection.rollback();
            return res.status(400).json({ status: 'error', message: 'ID Servis harus disertakan' });
        }

        const [srv] = await connection.execute(
            `SELECT id, no_nota, status, status_pembayaran, total_biaya FROM services WHERE id = ? FOR UPDATE`,
            [service_id]
        );

        if (srv.length === 0) {
            await connection.rollback();
            return res.status(404).json({ status: 'error', message: 'Servis tidak ditemukan' });
        }

        const status_lama = srv[0].status;
        const totalBiaya = parseFloat(srv[0].total_biaya || 0);

        const statusBayar = ['lunas', 'dp', 'belum_bayar'].includes(status_pembayaran) ? status_pembayaran : 'belum_bayar';
        const metode = metode_pembayaran || 'Cash';
        const nominal = parseFloat(nominal_dibayar) || 0;
        const dp = statusBayar === 'dp' ? (parseFloat(dp_dibayar) || nominal) : (statusBayar === 'lunas' ? 0 : 0);

        // Hitung kembalian otomatis jika tidak dikirimkan eksplisit
        let computedKembalian = parseFloat(kembalian);
        if (isNaN(computedKembalian)) {
            if (statusBayar === 'lunas') {
                computedKembalian = Math.max(0, nominal - totalBiaya);
            } else if (statusBayar === 'dp') {
                computedKembalian = Math.max(0, nominal - dp);
            } else {
                computedKembalian = 0;
            }
        }

        // Update status unit jika kasir mencentang ambil_unit atau lunas
        let nextStatusUnit = status_lama;
        if (ambil_unit === true || (statusBayar === 'lunas' && ambil_unit !== false)) {
            nextStatusUnit = 'diambil';
        }

        await connection.execute(`
            UPDATE services 
            SET 
                status = ?,
                status_pembayaran = ?,
                metode_pembayaran = ?,
                nominal_dibayar = ?,
                dp_dibayar = ?,
                kembalian = ?
            WHERE id = ?
        `, [nextStatusUnit, statusBayar, metode, nominal, dp, computedKembalian, service_id]);

        // Catat riwayat log transaksi pembayaran secara transparan
        let logNote = `Pembayaran diperbarui: Status [${statusBayar.toUpperCase()}], Metode [${metode}]. `;
        logNote += `Nominal Diterima: Rp ${nominal.toLocaleString('id-ID')}, `;
        if (statusBayar === 'dp') {
            logNote += `DP: Rp ${dp.toLocaleString('id-ID')}, Sisa: Rp ${(Math.max(0, totalBiaya - dp)).toLocaleString('id-ID')}. `;
        }
        logNote += `Kembalian: Rp ${computedKembalian.toLocaleString('id-ID')}. `;
        if (catatan) logNote += `Catatan: ${catatan}. `;
        if (nextStatusUnit === 'diambil') logNote += `Unit telah diserahkan ke pelanggan.`;

        await connection.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis) VALUES (?, ?, ?, ?)`,
            [service_id, status_lama, nextStatusUnit, logNote]
        );

        await connection.commit();
        res.json({
            status: 'success',
            message: `Pembayaran berhasil disimpan (${statusBayar.toUpperCase()})`,
            data: {
                service_id,
                status_pembayaran: statusBayar,
                metode_pembayaran: metode,
                nominal_dibayar: nominal,
                kembalian: computedKembalian,
                status_unit: nextStatusUnit
            }
        });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ status: 'error', message: error.message });
    } finally {
        connection.release();
    }
});

// Backward compatibility: PUT /api/admin/service/pelunasan
router.put('/service/pelunasan', async (req, res) => {
    // Alihkan ke endpoint pembayaran dengan flag lunas
    req.body.status_pembayaran = 'lunas';
    req.body.ambil_unit = true;
    const [srv] = await db.execute(`SELECT total_biaya FROM services WHERE id = ?`, [req.body.service_id]);
    if (srv.length > 0 && (!req.body.nominal_dibayar || req.body.nominal_dibayar === 0)) {
        req.body.nominal_dibayar = srv[0].total_biaya;
        req.body.kembalian = 0;
    }
    
    // Redirect internal logic
    const nextHandler = router.stack.find(layer => layer.route && layer.route.path === '/service/pembayaran');
    if (nextHandler) {
        return nextHandler.route.stack[0].handle(req, res);
    }
});

// 5. GET /api/admin/nota/:no_nota (Data Nota Lengkap beserta Rincian Onderdil)
router.get('/nota/:no_nota', async (req, res) => {
    try {
        const { no_nota } = req.params;
        const assignment = technicianScope(req.authUser, 's');
        const [rows] = await db.execute(`
            SELECT 
                s.id, s.no_nota, s.jenis_unit, s.merek_tipe, s.no_seri, s.kelengkapan, s.keluhan,
                s.status, s.biaya_jasa, s.total_biaya, s.status_pembayaran, s.metode_pembayaran,
                s.dp_dibayar, s.nominal_dibayar, s.kembalian, s.created_at,
                s.teknisi_id,
                u.nama AS nama_teknisi,
                c.nama AS nama_pelanggan, c.no_wa, c.alamat
            FROM services s
            JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.teknisi_id = u.id
            WHERE s.no_nota = ?${assignment.clause}
        `, [no_nota, ...assignment.params]);

        if (rows.length === 0) {
            return res.status(404).json({ status: 'error', message: 'Nota tidak ditemukan' });
        }

        const notaData = rows[0];

        // Ambil rincian sparepart yang terpasang
        const [details] = await db.execute(`
            SELECT sd.id, sd.jumlah, sd.harga_satuan, sd.subtotal, sp.nama_barang, sp.kode_barang
            FROM service_details sd
            JOIN spareparts sp ON sd.sparepart_id = sp.id
            WHERE sd.service_id = ?
        `, [notaData.id]);

        notaData.onderdil = details;

        res.json({ status: 'success', data: notaData });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

module.exports = router;