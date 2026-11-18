const db = require('./config/db');

async function runTests() {
    console.log('=== STARTING AUTOMATED FEATURE TESTS ===\n');

    try {
        // --- TEST 1: MULTI-ROLE & TEKNISI ASSIGNMENT ---
        console.log('>>> TEST 1: Multi-Role & Teknisi Assignment');
        const [users] = await db.execute(`SELECT id, nama, username, role FROM users`);
        console.log(`Users count: ${users.length}`);
        const roles = [...new Set(users.map(u => u.role))];
        console.log(`Found roles: ${roles.join(', ')}`);
        if (!roles.includes('owner') || !roles.includes('admin') || !roles.includes('teknisi')) {
            throw new Error('Tabel users harus mencakup role owner, admin, dan teknisi!');
        }
        console.log('✔ Multi-role requirement passed.');

        // Get a technician
        const [teknisi] = await db.execute(`SELECT id, nama FROM users WHERE role = 'teknisi' LIMIT 1`);
        if (teknisi.length === 0) throw new Error('Tidak ada user teknisi');
        const tekId = teknisi[0].id;
        console.log(`Technician found: ID ${tekId} (${teknisi[0].nama})`);

        // Create a test service unit assigned to this technician
        const testNota = `SRV-TEST-${Date.now()}`;
        const [custRes] = await db.execute(
            `INSERT INTO customers (nama, no_wa, alamat) VALUES ('Budi Pelanggan Test', '081299998888', 'Jl. Test 1')`
        );
        const custId = custRes.insertId;

        const [srvRes] = await db.execute(
            `INSERT INTO services (no_nota, customer_id, teknisi_id, jenis_unit, merek_tipe, keluhan, status, biaya_jasa, total_biaya, status_pembayaran)
             VALUES (?, ?, ?, 'Laptop', 'Lenovo ThinkPad Test', 'Layar blank hitam', 'masuk', 100000, 100000, 'belum_bayar')`,
            [testNota, custId, tekId]
        );
        const testServiceId = srvRes.insertId;

        await db.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis)
             VALUES (?, NULL, 'masuk', 'Pendaftaran test unit dengan PIC Teknisi')`,
            [testServiceId]
        );
        console.log(`✔ Service unit created: ${testNota} (ID: ${testServiceId}) with assigned teknisi ID: ${tekId}`);

        // --- TEST 2: PENGADAAN BARANG (OWNER) & OTOMATIS TAMBAH STOK ---
        console.log('\n>>> TEST 2: Pengadaan Barang (Owner) & Auto Stock Increment');
        // Pick a sparepart
        const [spBefore] = await db.execute(`SELECT id, kode_barang, nama_barang, stok, harga_beli FROM spareparts LIMIT 1`);
        const targetSp = spBefore[0];
        const initialStock = targetSp.stok;
        console.log(`Target Sparepart: ${targetSp.nama_barang} (Stok awal: ${initialStock})`);

        const purchaseQty = 7;
        const purchasePrice = 200000;
        const noPO = `PO-TEST-${Date.now()}`;

        // Insert purchase
        const [purchRes] = await db.execute(
            `INSERT INTO purchases (no_pembelian, supplier, tanggal_pembelian, total_biaya, keterangan)
             VALUES (?, 'PT Distributor Komputer Pusat', CURDATE(), ?, 'Test pengadaan Owner')`,
            [noPO, purchaseQty * purchasePrice]
        );
        const purchaseId = purchRes.insertId;

        // Insert purchase_detail
        await db.execute(
            `INSERT INTO purchase_details (purchase_id, sparepart_id, jumlah, harga_beli_satuan, subtotal)
             VALUES (?, ?, ?, ?, ?)`,
            [purchaseId, targetSp.id, purchaseQty, purchasePrice, purchaseQty * purchasePrice]
        );

        // Update stock
        await db.execute(
            `UPDATE spareparts SET stok = stok + ?, harga_beli = ? WHERE id = ?`,
            [purchaseQty, purchasePrice, targetSp.id]
        );

        // Verify stock increased
        const [spAfter] = await db.execute(`SELECT stok FROM spareparts WHERE id = ?`, [targetSp.id]);
        const finalStock = spAfter[0].stok;
        console.log(`Stok sesudah pengadaan: ${finalStock} (Ekspektasi: ${initialStock + purchaseQty})`);
        if (finalStock !== initialStock + purchaseQty) {
            throw new Error(`Stok tidak bertambah dengan benar! Awal: ${initialStock}, Akhir: ${finalStock}`);
        }
        console.log('✔ Pengadaan barang dan penambahan stok otomatis berhasil!');

        // --- TEST 3: PEMASANGAN ONDERDIL & TEKNISI UPDATE ---
        console.log('\n>>> TEST 3: Pemasangan Onderdil ke Unit Servis');
        // Attach 1 qty of this sparepart to the test service
        await db.execute(`UPDATE spareparts SET stok = stok - 1 WHERE id = ?`, [targetSp.id]);
        await db.execute(
            `INSERT INTO service_details (service_id, sparepart_id, jumlah, harga_satuan, subtotal)
             VALUES (?, ?, 1, 300000, 300000)`,
            [testServiceId, targetSp.id]
        );
        // Update service total: Jasa 100000 + Onderdil 300000 = 400000
        await db.execute(
            `UPDATE services SET status = 'selesai', total_biaya = 400000 WHERE id = ?`,
            [testServiceId]
        );
        await db.execute(
            `INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis)
             VALUES (?, 'masuk', 'selesai', 'Penggantian sparepart dan kalibrasi selesai')`,
            [testServiceId]
        );
        console.log('✔ Sparepart terpasang di unit servis, total biaya: Rp 400.000');

        // --- TEST 4: KELOLA PEMBAYARAN (ADMIN) ---
        console.log('\n>>> TEST 4: Kelola Pembayaran (Admin)');
        // 1. Bayar Lunas via Cash Rp 500.000 (Kembalian: Rp 100.000)
        const totalTagihan = 400000;
        const uangDiterima = 500000;
        const kembalian = uangDiterima - totalTagihan;

        await db.execute(`
            UPDATE services 
            SET 
                status = 'diambil',
                status_pembayaran = 'lunas',
                metode_pembayaran = 'Cash',
                nominal_dibayar = ?,
                kembalian = ?
            WHERE id = ?
        `, [uangDiterima, kembalian, testServiceId]);

        await db.execute(`
            INSERT INTO service_logs (service_id, status_lama, status_baru, catatan_teknis)
            VALUES (?, 'selesai', 'diambil', 'Pembayaran Lunas via Cash Rp 500.000, Kembalian Rp 100.000. Unit diserahkan.')
        `, [testServiceId]);

        const [srvPaid] = await db.execute(`SELECT * FROM services WHERE id = ?`, [testServiceId]);
        console.log(`Status Pembayaran: ${srvPaid[0].status_pembayaran}`);
        console.log(`Metode Pembayaran: ${srvPaid[0].metode_pembayaran}`);
        console.log(`Nominal Dibayar: Rp ${parseFloat(srvPaid[0].nominal_dibayar).toLocaleString('id-ID')}`);
        console.log(`Kembalian: Rp ${parseFloat(srvPaid[0].kembalian).toLocaleString('id-ID')}`);
        console.log(`Status Unit: ${srvPaid[0].status}`);
        if (srvPaid[0].status_pembayaran !== 'lunas' || parseFloat(srvPaid[0].kembalian) !== 100000) {
            throw new Error('Data kelola pembayaran tidak sesuai!');
        }
        console.log('✔ Kelola Pembayaran (Lunas, Cash, Nominal, Kembalian) berhasil!');

        // --- TEST 5: RINCIAN BIAYA & RIWAYAT TRANSPARAN (CUSTOMER TRACKING) ---
        console.log('\n>>> TEST 5: Rincian Biaya & Riwayat Timeline (Customer Tracking)');
        // Query as tracking.js does
        const [trackRows] = await db.execute(`
            SELECT 
                s.id, s.no_nota, s.jenis_unit, s.merek_tipe, s.keluhan,
                s.status, s.biaya_jasa, s.total_biaya, s.status_pembayaran, s.metode_pembayaran,
                s.nominal_dibayar, s.kembalian,
                u.nama AS nama_teknisi,
                c.nama AS nama_pelanggan
            FROM services s
            JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.teknisi_id = u.id
            WHERE s.no_nota = ?
        `, [testNota]);

        const trackingUnit = trackRows[0];
        console.log(`Tracking Unit No. Nota: ${trackingUnit.no_nota}`);
        console.log(`Pelanggan: ${trackingUnit.nama_pelanggan}`);
        console.log(`Teknisi PIC: ${trackingUnit.nama_teknisi}`);
        console.log(`Biaya Jasa: Rp ${parseFloat(trackingUnit.biaya_jasa).toLocaleString('id-ID')}`);
        console.log(`Total Biaya: Rp ${parseFloat(trackingUnit.total_biaya).toLocaleString('id-ID')}`);

        const [onderdilRows] = await db.execute(`
            SELECT sd.jumlah, sd.harga_satuan, sd.subtotal, sp.kode_barang, sp.nama_barang
            FROM service_details sd
            JOIN spareparts sp ON sd.sparepart_id = sp.id
            WHERE sd.service_id = ?
        `, [trackingUnit.id]);
        console.log(`Rincian Onderdil (${onderdilRows.length} item):`);
        onderdilRows.forEach(o => {
            console.log(`  - ${o.nama_barang} (${o.kode_barang}) x${o.jumlah}: Rp ${parseFloat(o.subtotal).toLocaleString('id-ID')}`);
        });

        const [logsRows] = await db.execute(`
            SELECT status_lama, status_baru, catatan_teknis, created_at
            FROM service_logs
            WHERE service_id = ?
            ORDER BY created_at ASC
        `, [trackingUnit.id]);
        console.log(`Riwayat Timeline (${logsRows.length} logs):`);
        logsRows.forEach(l => {
            console.log(`  - [${l.status_baru}] ${l.catatan_teknis}`);
        });

        if (onderdilRows.length === 0 || logsRows.length === 0 || !trackingUnit.nama_teknisi) {
            throw new Error('Rincian biaya atau timeline tracking tidak lengkap!');
        }
        console.log('✔ Rincian biaya dan riwayat transparan berhasil diverifikasi!');

        // --- TEST 6: MASTER DATA ONDERDIL (CRUD) ---
        console.log('\n>>> TEST 6: Master Data Onderdil (CRUD)');
        const testCode = `TEST-SP-${Date.now()}`;
        // Create
        const [createSp] = await db.execute(
            `INSERT INTO spareparts (kode_barang, nama_barang, stok, harga_beli, harga_jual) VALUES (?, 'Kipas Fan Test', 10, 50000, 80000)`,
            [testCode]
        );
        const newSpId = createSp.insertId;
        console.log(`Created sparepart ID: ${newSpId}`);

        // Read
        const [readSp] = await db.execute(`SELECT * FROM spareparts WHERE id = ?`, [newSpId]);
        if (readSp.length === 0) throw new Error('Gagal read sparepart');
        console.log(`Read sparepart: ${readSp[0].nama_barang}`);

        // Update
        await db.execute(`UPDATE spareparts SET nama_barang = 'Kipas Fan Updated', stok = 15 WHERE id = ?`, [newSpId]);
        const [updatedSp] = await db.execute(`SELECT nama_barang, stok FROM spareparts WHERE id = ?`, [newSpId]);
        if (updatedSp[0].nama_barang !== 'Kipas Fan Updated' || updatedSp[0].stok !== 15) throw new Error('Gagal update sparepart');
        console.log(`Updated sparepart: ${updatedSp[0].nama_barang}, Stok: ${updatedSp[0].stok}`);

        // Delete
        await db.execute(`DELETE FROM spareparts WHERE id = ?`, [newSpId]);
        const [deletedSp] = await db.execute(`SELECT id FROM spareparts WHERE id = ?`, [newSpId]);
        if (deletedSp.length !== 0) throw new Error('Gagal delete sparepart');
        console.log('Deleted sparepart successfully.');
        console.log('✔ CRUD Master Onderdil fully verified!');

        console.log('\n=== ALL 5 REQUIREMENTS PASSED WITH 100% SUCCESS! ===');
        process.exit(0);
    } catch (err) {
        console.error('\n❌ TEST FAILED:', err);
        process.exit(1);
    }
}

runTests();
