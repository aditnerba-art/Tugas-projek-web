const db = require('./db');
const { hashPassword, isPasswordHash } = require('../auth');

async function migrate() {
    try {
        console.log('Running database setup and migrations...');

        // 1. Create purchases table
        await db.execute(`
            CREATE TABLE IF NOT EXISTS purchases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                no_pembelian VARCHAR(50) NOT NULL UNIQUE,
                supplier VARCHAR(100) NOT NULL,
                tanggal_pembelian DATE NOT NULL,
                total_biaya DECIMAL(12,2) NOT NULL DEFAULT 0,
                keterangan TEXT,
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);
        console.log('Table purchases is ready');

        // 2. Create purchase_details table
        await db.execute(`
            CREATE TABLE IF NOT EXISTS purchase_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                purchase_id INT NOT NULL,
                sparepart_id INT NOT NULL,
                jumlah INT NOT NULL,
                harga_beli_satuan DECIMAL(12,2) NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
                FOREIGN KEY (sparepart_id) REFERENCES spareparts(id) ON DELETE RESTRICT
            )
        `);
        console.log('Table purchase_details is ready');

        // 3. Add nominal_dibayar and kembalian columns to services if not present
        const [cols] = await db.execute('DESCRIBE services');
        const colNames = cols.map(c => c.Field);

        if (!colNames.includes('nominal_dibayar')) {
            await db.execute(`ALTER TABLE services ADD COLUMN nominal_dibayar DECIMAL(12,2) DEFAULT 0 AFTER dp_dibayar`);
            console.log('Added nominal_dibayar to services');
        }

        if (!colNames.includes('kembalian')) {
            await db.execute(`ALTER TABLE services ADD COLUMN kembalian DECIMAL(12,2) DEFAULT 0 AFTER nominal_dibayar`);
            console.log('Added kembalian to services');
        }

        // 4. Seed default users if users table is empty
        const [users] = await db.execute('SELECT COUNT(*) as count FROM users');
        if (users[0].count === 0) {
            await db.execute(`
                INSERT INTO users (nama, username, password, role) VALUES
                ('Pak Hendra (Owner Toko)', 'owner', 'owner123', 'owner'),
                ('Siti Rahma (Admin Kasir)', 'admin', 'admin123', 'admin'),
                ('Budi Santoso (Teknisi Laptop)', 'budi_teknisi', 'teknisi123', 'teknisi'),
                ('Andi Wijaya (Teknisi PC & Hardware)', 'andi_teknisi', 'teknisi123', 'teknisi')
            `);
            console.log('Default users seeded successfully (owner, admin, teknisi1, teknisi2)');
        }

        // Store sessions by a one-way hash of a random cookie token.
        await db.execute(`
            CREATE TABLE IF NOT EXISTS auth_sessions (
                token_hash CHAR(64) NOT NULL PRIMARY KEY,
                user_id INT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_auth_sessions_user_id (user_id),
                INDEX idx_auth_sessions_expires_at (expires_at)
            )
        `);

        // Pastikan kolom token_hash, user_id, dan expires_at ada pada auth_sessions
        const [authCols] = await db.execute('DESCRIBE auth_sessions').catch(() => [[]]);
        const authColNames = authCols.map(c => c.Field);
        if (authColNames.length > 0) {
            if (!authColNames.includes('token_hash')) {
                await db.execute('ALTER TABLE auth_sessions ADD COLUMN token_hash CHAR(64) NOT NULL PRIMARY KEY');
                console.log('Added token_hash to auth_sessions');
            }
            if (!authColNames.includes('user_id')) {
                await db.execute('ALTER TABLE auth_sessions ADD COLUMN user_id INT NOT NULL');
                console.log('Added user_id to auth_sessions');
            }
            if (!authColNames.includes('expires_at')) {
                await db.execute('ALTER TABLE auth_sessions ADD COLUMN expires_at DATETIME NOT NULL');
                console.log('Added expires_at to auth_sessions');
            }
        }
        console.log('Table auth_sessions is ready');

        await db.execute(`
            CREATE TABLE IF NOT EXISTS service_access_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                revoked_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_service_access_service_id (service_id)
            )
        `);
        await db.execute(`
            CREATE TABLE IF NOT EXISTS customer_sessions (
                session_hash CHAR(64) NOT NULL PRIMARY KEY,
                access_token_id INT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_customer_sessions_access_token (access_token_id),
                INDEX idx_customer_sessions_expires_at (expires_at)
            )
        `);

        // Upgrade existing plaintext credentials once; existing usernames remain unchanged.
        const [credentialRows] = await db.execute('SELECT id, password FROM users');
        for (const account of credentialRows) {
            if (typeof account.password !== 'string' || isPasswordHash(account.password)) continue;
            const passwordHash = await hashPassword(account.password);
            await db.execute('UPDATE users SET password = ? WHERE id = ?', [passwordHash, account.id]);
        }
        console.log('Authentication sessions and password hashes are ready');

        // 5. Update existing test services with teknisi_id if null
        const [teknisiRows] = await db.execute(`SELECT id FROM users WHERE role = 'teknisi' LIMIT 1`);
        if (teknisiRows.length > 0) {
            await db.execute(`UPDATE services SET teknisi_id = ? WHERE teknisi_id IS NULL`, [teknisiRows[0].id]);
            console.log('Updated existing services with assigned technician');
        }

        // 6. If service SRV-001 has no service_details, add a sample sparepart detail for testing transparent tracking breakdown
        const [srv1] = await db.execute(`SELECT id FROM services WHERE no_nota = 'SRV-001'`);
        if (srv1.length > 0) {
            const [existingDetails] = await db.execute(`SELECT COUNT(*) as cnt FROM service_details WHERE service_id = ?`, [srv1[0].id]);
            if (existingDetails[0].cnt === 0) {
                // Add sample detail: RAM DDR4 8GB and update biaya_jasa
                const [spRow] = await db.execute(`SELECT id, harga_jual FROM spareparts LIMIT 1`);
                if (spRow.length > 0) {
                    const harga = parseFloat(spRow[0].harga_jual);
                    const jasa = 150000;
                    await db.execute(`
                        INSERT INTO service_details (service_id, sparepart_id, jumlah, harga_satuan, subtotal)
                        VALUES (?, ?, 1, ?, ?)
                    `, [srv1[0].id, spRow[0].id, harga, harga]);
                    await db.execute(`
                        UPDATE services SET biaya_jasa = ?, total_biaya = ? WHERE id = ?
                    `, [jasa, (jasa + harga), srv1[0].id]);
                    console.log('Populated sample service_details for SRV-001 testing');
                }
            }
        }

        console.log('All migrations executed successfully!');
    } catch (err) {
        console.error('Migration error:', err);
        throw err;
    }
}

if (require.main === module) {
    migrate().then(() => process.exit(0)).catch(() => process.exit(1));
}

module.exports = migrate;
