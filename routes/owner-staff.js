const express = require('express');
const { hashPassword } = require('../auth');

function createOwnerStaffRouter(db) {
    const router = express.Router();

    // 1. GET /api/owner/staff (Daftar Akun Admin & Teknisi)
    router.get('/', async (req, res) => {
        try {
            const [users] = await db.execute(
                `SELECT id, nama, username, role FROM users
                 WHERE role IN ('admin', 'teknisi') ORDER BY role ASC, nama ASC`
            );
            return res.json({ status: 'success', users });
        } catch (error) {
            console.error('Owner staff list failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Daftar akun staf gagal dimuat' });
        }
    });

    // 2. POST /api/owner/staff (Tambah Akun Staf Baru: Admin atau Teknisi)
    router.post('/', async (req, res) => {
        try {
            const rawNama = typeof req.body?.nama === 'string' ? req.body.nama.trim() : '';
            const rawUsername = typeof req.body?.username === 'string' ? req.body.username.trim() : '';
            const rawPassword = typeof req.body?.password === 'string' ? req.body.password : '';
            const rawRole = typeof req.body?.role === 'string' ? req.body.role.trim().toLowerCase() : '';

            // Validasi Role (wajib 'admin' atau 'teknisi')
            if (!['admin', 'teknisi'].includes(rawRole)) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Role staf tidak valid. Pilih antara "admin" atau "teknisi"'
                });
            }

            // Validasi Nama (wajib, min. 2 karakter, maks. 100 karakter)
            if (!rawNama || rawNama.length < 2) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Nama lengkap wajib diisi (minimal 2 karakter)'
                });
            }
            if (rawNama.length > 100) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Nama lengkap maksimal 100 karakter'
                });
            }

            // Validasi Username (wajib, 3–32 karakter alfanumerik/titik/garis bawah/strip)
            if (!rawUsername || !/^[a-zA-Z0-9_.-]{3,32}$/.test(rawUsername)) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Username harus 3–32 karakter alfanumerik (huruf, angka, titik, strip, atau garis bawah)'
                });
            }

            // Validasi Username Unik di tabel users
            const [duplicateUsers] = await db.execute(
                `SELECT id FROM users WHERE username = ? LIMIT 1`,
                [rawUsername]
            );
            if (duplicateUsers.length > 0) {
                return res.status(409).json({
                    status: 'error',
                    message: `Username "${rawUsername}" sudah digunakan oleh akun lain`
                });
            }

            // Validasi Password (wajib, min. 8 karakter)
            if (!rawPassword || rawPassword.length < 8 || Buffer.byteLength(rawPassword, 'utf8') > 1024) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Password wajib diisi (minimal 8 karakter)'
                });
            }

            // Hash password dengan scrypt
            const passwordHash = await hashPassword(rawPassword);

            // Simpan akun staf baru ke tabel users
            const [insertResult] = await db.execute(
                `INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)`,
                [rawNama, rawUsername, passwordHash, rawRole]
            );

            return res.status(201).json({
                status: 'success',
                message: `Akun ${rawRole.toUpperCase()} "${rawNama}" berhasil ditambahkan`,
                user: {
                    id: insertResult.insertId,
                    nama: rawNama,
                    username: rawUsername,
                    role: rawRole
                }
            });
        } catch (error) {
            if (error.code === 'ER_DUP_ENTRY') {
                return res.status(409).json({ status: 'error', message: 'Username sudah digunakan' });
            }
            console.error('Owner add staff failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Gagal menambahkan akun staf' });
        }
    });

    // 3. PATCH & PUT /api/owner/staff/:id (Edit Akun Staf: Admin & Teknisi)
    async function handleUpdateStaff(req, res) {
        const userId = Number(req.params.id);
        if (!Number.isSafeInteger(userId) || userId < 1) {
            return res.status(400).json({ status: 'error', message: 'ID akun tidak valid' });
        }

        try {
            // Validasi: hanya bisa memperbarui user dengan role IN ('admin', 'teknisi')
            const [rows] = await db.execute(
                `SELECT id, nama, username, role FROM users WHERE id = ? AND role IN ('admin', 'teknisi') LIMIT 1`,
                [userId]
            );
            const user = rows[0];
            if (!user) {
                return res.status(404).json({ status: 'error', message: 'Akun staf tidak ditemukan' });
            }

            // Ambil input nama, username, dan password
            const rawNama = typeof req.body?.nama === 'string'
                ? req.body.nama.trim()
                : (typeof req.body?.name === 'string' ? req.body.name.trim() : '');
            const rawUsername = typeof req.body?.username === 'string'
                ? req.body.username.trim()
                : (typeof req.body?.newUsername === 'string' ? req.body.newUsername.trim() : '');
            const rawPassword = typeof req.body?.password === 'string'
                ? req.body.password
                : (typeof req.body?.newPassword === 'string' ? req.body.newPassword : '');

            // Validasi nama wajib diisi (minimal 2 karakter)
            const nama = rawNama !== '' ? rawNama : user.nama;
            if (!nama || nama.length < 2) {
                return res.status(400).json({ status: 'error', message: 'Nama wajib diisi (minimal 2 karakter)' });
            }
            if (nama.length > 100) {
                return res.status(400).json({ status: 'error', message: 'Nama maksimal 100 karakter' });
            }

            // Validasi format username
            const username = rawUsername !== '' ? rawUsername : user.username;
            if (!username || !/^[a-zA-Z0-9_.-]{3,32}$/.test(username)) {
                return res.status(400).json({
                    status: 'error',
                    message: 'Username harus 3–32 karakter alfanumerik (huruf, angka, titik, strip, atau garis bawah)'
                });
            }

            // Validasi username unik (cek duplikat di tabel users selain ID yang sedang diedit)
            if (username !== user.username) {
                const [duplicates] = await db.execute(
                    'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1',
                    [username, userId]
                );
                if (duplicates.length > 0) {
                    return res.status(409).json({ status: 'error', message: 'Username sudah digunakan' });
                }
            }

            // Validasi password baru jika diisi (minimal 8 karakter)
            if (rawPassword && (rawPassword.length < 8 || Buffer.byteLength(rawPassword, 'utf8') > 1024)) {
                return res.status(400).json({ status: 'error', message: 'Kata sandi baru minimal 8 karakter' });
            }

            // Simpan perubahan ke database
            const hasNamaInput = typeof req.body?.nama === 'string' || typeof req.body?.name === 'string';
            if (rawPassword) {
                const passwordHash = await hashPassword(rawPassword);
                if (hasNamaInput) {
                    await db.execute(
                        'UPDATE users SET nama = ?, username = ?, password = ? WHERE id = ?',
                        [nama, username, passwordHash, userId]
                    );
                } else {
                    await db.execute(
                        'UPDATE users SET username = ?, password = ? WHERE id = ?',
                        [username, passwordHash, userId]
                    );
                }
                // Jika password diubah, hapus sesi login aktif milik staf di tabel auth_sessions
                await db.execute('DELETE FROM auth_sessions WHERE user_id = ?', [userId]);
            } else {
                if (hasNamaInput) {
                    await db.execute(
                        'UPDATE users SET nama = ?, username = ? WHERE id = ?',
                        [nama, username, userId]
                    );
                } else {
                    await db.execute(
                        'UPDATE users SET username = ? WHERE id = ?',
                        [username, userId]
                    );
                }
            }

            return res.json({
                status: 'success',
                message: 'Data akun staf berhasil diperbarui',
                user: { id: userId, nama, username, role: user.role }
            });
        } catch (error) {
            if (error.code === 'ER_DUP_ENTRY') {
                return res.status(409).json({ status: 'error', message: 'Username sudah digunakan' });
            }
            console.error('Owner staff update failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Perubahan akun staf gagal disimpan' });
        }
    }

    router.patch('/:id', handleUpdateStaff);
    router.put('/:id', handleUpdateStaff);
    router.patch('/:id/credentials', handleUpdateStaff);

    // 4. DELETE /api/owner/staff/:id (Hapus Akun Staf: Admin & Teknisi)
    router.delete('/:id', async (req, res) => {
        const userId = Number(req.params.id);
        if (!Number.isSafeInteger(userId) || userId < 1) {
            return res.status(400).json({ status: 'error', message: 'ID akun tidak valid' });
        }

        // Cegah Owner menghapus akunnya sendiri
        if (req.authUser && req.authUser.id === userId) {
            return res.status(400).json({ status: 'error', message: 'Anda tidak dapat menghapus akun Anda sendiri' });
        }

        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();

            // Cek data akun staf yang ingin dihapus
            const [rows] = await connection.execute(
                `SELECT id, nama, username, role FROM users WHERE id = ? FOR UPDATE`,
                [userId]
            );
            const user = rows[0];
            if (!user) {
                await connection.rollback();
                return res.status(404).json({ status: 'error', message: 'Akun staf tidak ditemukan' });
            }

            // Validasi: hanya role 'admin' atau 'teknisi' yang boleh dihapus
            if (!['admin', 'teknisi'].includes(user.role)) {
                await connection.rollback();
                return res.status(403).json({
                    status: 'error',
                    message: 'Hanya akun admin dan teknisi yang dapat dihapus'
                });
            }

            // 1. Hapus sesi aktif staf di auth_sessions
            await connection.execute(`DELETE FROM auth_sessions WHERE user_id = ?`, [userId]);

            // 2. Proteksi Relasi Database: Jika teknisi masih terhubung ke services, set teknisi_id = NULL
            await connection.execute(`UPDATE services SET teknisi_id = NULL WHERE teknisi_id = ?`, [userId]);

            // 3. Proteksi Relasi Database: Jika user terhubung ke created_by di purchases, set created_by = NULL
            await connection.execute(`UPDATE purchases SET created_by = NULL WHERE created_by = ?`, [userId]);

            // 4. Hapus user dari tabel users
            await connection.execute(`DELETE FROM users WHERE id = ?`, [userId]);

            await connection.commit();

            return res.json({
                status: 'success',
                message: `Akun ${user.role.toUpperCase()} "${user.nama}" berhasil dihapus`
            });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Owner delete staff failed:', error.message);
            return res.status(500).json({ status: 'error', message: 'Gagal menghapus akun staf' });
        } finally {
            if (connection) connection.release();
        }
    });

    return router;
}

module.exports = { createOwnerStaffRouter };
