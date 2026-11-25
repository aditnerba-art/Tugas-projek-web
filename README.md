# TECKFO Service

Aplikasi servis komputer berbasis Laravel 12 dengan portal pelanggan, panel admin/kasir, panel teknisi, dan dashboard owner.

### Instalasi dan menjalankan aplikasi

Persyaratan: PHP 8.2+, Composer, Node.js >= 20.19 dan npm, serta ekstensi PHP `fileinfo`, `gd`, `mbstring`, `zip`, `openssl`, dan driver `pdo_mysql` atau `pdo_sqlite` sesuai database. `zip` dan `openssl` diperlukan untuk backup terenkripsi. Dari root proyek:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

Jika berkas `.env` sudah ada, jangan menyalinnya lagi; cukup tinjau konfigurasi yang diperlukan. SQLite digunakan secara default. Untuk MySQL/MariaDB, ubah `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`, lalu pastikan databasenya sudah dibuat sebelum menjalankan migrasi.

Seeder akun awal hanya membuat akun staf dengan kewajiban mengganti kata sandi saat login pertama. Untuk mengisi data servis contoh (delapan status berbeda), jalankan secara terpisah:

```sh
php artisan db:seed --class=DemoServiceSeeder
```

Seeder demo dapat dijalankan ulang tanpa menggandakan servis contoh. Gunakan hanya di lingkungan pengembangan atau demo.

### Realtime dan notifikasi

Status servis dicatat di timeline terpusat dan dikirim melalui Laravel Reverb saat `REALTIME_DRIVER=reverb`. Staf memakai private channel sesuai peran. Portal pelanggan menerima nama channel opaque berbasis HMAC hanya dari sesi portal yang sudah valid; endpoint polling tetap memverifikasi sesi dan token akses pelanggan. Jika WebSocket tidak tersambung, frontend otomatis memakai polling JSON dengan ETag setiap 12 detik.

### Queue, Reverb, scheduler, dan variabel lingkungan

Notifikasi WhatsApp dan event broadcast diproses melalui queue. Di lokal, `composer dev` menjalankan server Laravel, queue worker, Pail, Vite, dan Reverb. Untuk menjalankannya sebagai proses terpisah:

```sh
php artisan serve
php artisan queue:work --queue=realtime,default --sleep=1 --tries=3 --timeout=45
php artisan reverb:start
php artisan schedule:work
```

Event status staf dan pelanggan diproses asinkron pada antrean `realtime`, yang diprioritaskan di atas `default`. Pengiriman dicoba ulang 3 kali dengan jeda 1, 5, lalu 15 detik. `ShouldRescue` melaporkan dan menelan kegagalan saat event dimasukkan ke antrean; bila Reverb tidak tersedia saat worker mengirim, request status tetap selesai, worker mencoba ulang, lalu kegagalan akhir tercatat di `failed_jobs` dan log Laravel. Pilihan asinkron menghindari request menunggu koneksi Reverb (berbeda dari `ShouldBroadcastNow`), dengan trade-off bahwa event memerlukan worker aktif dan dapat tertunda selama retry.

| Kelompok | Variabel `.env` | Kegunaan |
| --- | --- | --- |
| Aplikasi | `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY`, `APP_NAME`, `APP_TIMEZONE`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Lingkungan, URL publik, enkripsi, nama, bahasa, dan zona waktu |
| Database | `DB_CONNECTION`, `DB_TIMEZONE`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Koneksi dan zona waktu sesi basis data; MySQL/MariaDB default `+07:00` |
| Worker | `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | Antrean, cache, dan sesi; konfigurasi default memakai database |
| Realtime | `BROADCAST_CONNECTION`, `REALTIME_DRIVER`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`, `REVERB_ALLOWED_ORIGINS` | Kredensial, listener, dan origin yang diizinkan untuk WebSocket |
| Build frontend realtime | `VITE_REALTIME_DRIVER`, `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | Ditanam saat `npm run build`; build ulang setelah nilainya diubah |
| Reverse proxy | `TRUSTED_PROXIES` | IP atau CIDR proxy HTTPS tepercaya, dipisahkan koma; kosongkan jika tidak memakai proxy |
| WhatsApp | `WHATSAPP_DRIVER`, `FONNTE_TOKEN`, `WABLAS_TOKEN`, `WHATSAPP_CLOUD_TOKEN`, `WHATSAPP_CLOUD_PHONE_NUMBER_ID` | Pilih `fonnte`, `wablas`, atau `cloud`, lalu isi kredensial providernya |
| Backup | `DB_BACKUP_DIRECTORY`, `DB_BACKUP_KEEP_DAYS`, `DB_PHOTO_BACKUP_DIRECTORY`, `DB_PHOTO_BACKUP_KEEP_DAYS`, `DB_BACKUP_MINIMUM_BYTES`, `MYSQLDUMP_BINARY`, `PG_DUMP_BINARY` | Direktori/retensi database dan foto, ukuran minimum dump, dan lokasi binary backup |
| Salinan backup off-site | `BACKUP_OFFSITE_DISK`, `BACKUP_OFFSITE_PREFIX`, `BACKUP_ENCRYPTION_KEY`, `AWS_*`, `SFTP_*` | Disk filesystem S3-compatible/SFTP, prefix remote, kredensial provider, dan kunci enkripsi arsip |

URL provider WhatsApp dapat dioverride lewat `FONNTE_URL`, `WABLAS_URL`, atau `WHATSAPP_CLOUD_URL`. Lihat `.env.example` dan `.env.production.example` untuk nilai awal; jangan salin kredensial contoh ke server publik.

Untuk menyiapkan Reverb lokal, salin blok `BROADCAST_CONNECTION`, `REALTIME_DRIVER`, `REVERB_*`, dan `VITE_REVERB_*` dari `.env.example` ke `.env` (atau buat ulang `.env` dari contoh sebelum mengisi rahasia lokal). Nilai `local` dan `local-secret` hanya untuk pengembangan lokal; jangan gunakan di produksi. Nilai `VITE_*` tidak rahasia dan dipakai saat build frontend, jadi jalankan ulang `npm run build` setelah mengubahnya.

`composer dev` tidak menjalankan scheduler, jadi mulai proses `schedule:work` secara terpisah saat menguji tugas terjadwal.

### Pengujian dan database CI

Workflow `.github/workflows/ci.yml` menjalankan `composer install`, `npm ci`/build Vite, Pint, dan seluruh suite dengan matrix SQLite in-memory serta MySQL 8 service container. Dengan begitu migrasi, transaksi, dan `lockForUpdate` diuji pada kedua driver.

Untuk pengujian MySQL lokal, arahkan variabel `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` ke database tes khusus yang kosong sebelum menjalankan `php artisan test`. Jangan arahkan tes ke database development atau produksi karena migrasi tes akan menghapus dan membuat ulang skema.

Di produksi, gunakan kredensial rahasia milik deployment, bukan nilai contoh di `.env.production.example`. Aplikasi menolak boot bila `APP_DEBUG=true` atau kunci/rahasia Reverb masih berupa placeholder. Jika TLS diakhiri oleh reverse proxy, isi `TRUSTED_PROXIES` dengan alamat IP/CIDR proxy yang benar (dipisahkan koma); jangan gunakan `*` atau mempercayai alamat dari klien. Pastikan Reverb menggunakan domain dan origin yang benar; terminasi TLS/WSS sebaiknya dilakukan oleh reverse proxy dan port listener Reverb hanya dibuka secara internal.

Contoh Supervisor (sesuaikan direktori dan pengguna deployment):

```ini
[program:teckfo-reverb]
directory=/var/www/teckfo
command=php artisan reverb:start --host=0.0.0.0 --port=8080
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/teckfo-reverb.log

[program:teckfo-queue]
directory=/var/www/teckfo
command=php artisan queue:work --queue=realtime,default --sleep=1 --tries=3 --timeout=45
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/teckfo-queue.log
```

Simpan konfigurasi Supervisor pada `/etc/supervisor/conf.d/teckfo.conf`, lalu jalankan `sudo supervisorctl reread`, `sudo supervisorctl update`, dan `sudo supervisorctl status`.

Alternatif `systemd` untuk Reverb (simpan sebagai `/etc/systemd/system/teckfo-reverb.service`):

```ini
[Unit]
Description=TECKFO Laravel Reverb
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/teckfo
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Aktifkan dengan `sudo systemctl daemon-reload && sudo systemctl enable --now teckfo-reverb`. Jalankan queue worker sebagai service terpisah juga. Untuk tugas terjadwal, jalankan `php artisan schedule:work` sebagai service atau pasang cron Laravel setiap menit seperti petunjuk di bagian backup.

### Jika memakai shared hosting

- Atur `REALTIME_DRIVER=polling` dan `VITE_REALTIME_DRIVER=polling`, lalu jalankan ulang `npm run build` setiap kali konfigurasi frontend realtime berubah. Mode ini tidak memerlukan proses Reverb persisten.
- Jika hosting tidak mendukung worker persisten, jalankan cron berikut setiap menit agar antrean diproses:

  ```cron
  * * * * * cd /path/aplikasi && php artisan queue:work --stop-when-empty --max-time=55
  ```

### Akun awal

| Peran | Username | Kata sandi |
| --- | --- | --- |
| Owner | `owner` | `owner123` |
| Admin | `admin` | `admin123` |
| Teknisi | `budi_teknisi` | `teknisi123` |

Ganti kata sandi awal sebelum aplikasi digunakan di lingkungan produksi.

Pelanggan menerima kode akses servis 5 digit saat servis didaftarkan. Portal publik tersedia di `/` dan `/lacak`; alamat lama `/index.html` dialihkan permanen ke beranda. Percobaan kode akses dan login staf dibatasi, dan persetujuan pelanggan memerlukan sesi portal yang sah.

Tampilan utama serta halaman pengelolaan sparepart menggunakan Blade; request `fetch()` menyertakan token CSRF Laravel. Akun seed wajib mengganti kata sandi saat login pertama. Owner dapat mengatur kata sandi staf dan melihat log aktivitas di `/owner/activity-logs`.

### Laporan, tabel panel, dan backup

Tabel panel mendukung pencarian, filter yang sesuai dengan datanya, pengurutan, dan pagination server-side. Laporan owner dapat difilter harian/bulanan. **Omzet mengikuti `paid_at`**: pembayaran menambah omzet dan refund menguranginya, termasuk untuk transaksi yang melintasi hari atau bulan pendaftaran servis. Laba kotor mengakui snapshot modal sparepart **secara proporsional** pada setiap pembayaran bersih: porsi modal = pembayaran bersih ÷ nilai tagihan × total modal sparepart servis; refund membalik porsi modal yang sama. Piutang per servis adalah tagihan dikurangi seluruh pembayaran bersih (pembayaran dikurangi refund). Jumlah/status servis, teknisi, dan sparepart tetap merujuk servis yang didaftarkan dalam rentang tanggal terpilih. Filter tanggal memakai zona waktu aplikasi (`APP_TIMEZONE`, default `Asia/Jakarta`). Laporan juga menampilkan rekonsiliasi kas harian per kasir, dengan transaksi tunai dan non-tunai bersih setelah refund. CSV dan halaman cetak/PDF menyertakan rincian tersebut. Ekspor CSV memakai pemisah `;` dan BOM UTF-8 agar terbuka benar di Excel dengan pengaturan regional Indonesia.

Pembayaran kasir disimpan sebagai transaksi rupiah integer dengan metode `tunai`, `transfer`, `qris`, atau `lainnya`; cicilan menambah pembayaran dan refund dicatat sebagai transaksi terpisah yang mengurangi total dibayar. Status servis (`belum`, `sebagian`, `lunas`) dihitung ulang dari ledger. Migrasi database mengimpor pembayaran lama dengan catatan `migrasi data lama`, memakai waktu aktivitas pembayaran jika tersedia atau timestamp servis sebagai fallback. Untuk mengulang backfill dengan aman, jalankan `php artisan payments:migrate-legacy`; transaksi yang sudah ada tidak digandakan.

Timestamp aplikasi ditulis/dibaca dalam konteks `APP_TIMEZONE`. Kolom `DATETIME` (termasuk `services.created_at`) menyimpan waktu kalender lokal tanpa konversi zona. Pada MySQL/MariaDB, kolom `TIMESTAMP` disimpan internal sebagai UTC oleh mesin database; `DB_TIMEZONE=+07:00` membuat sesi koneksi mengonversi nilai ke/dari WIB secara konsisten. Kolom `DATE` seperti tanggal pembelian dan akhir garansi adalah tanggal kalender tanpa jam. Respons API menserialisasi datetime sebagai ISO-8601 UTC, lalu browser mengubahnya ke zona waktu perangkat saat ditampilkan.

Backup otomatis dijalankan setiap hari pukul 02:00. Dump database disimpan di `storage/app/private/backups` dengan retensi default 14 hari, sedangkan snapshot ZIP foto servis di `storage/app/private/photo-backups` memiliki retensi terpisah default 30 hari. Keduanya berada di luar web root. Atur `DB_BACKUP_DIRECTORY`, `DB_BACKUP_KEEP_DAYS`, `DB_PHOTO_BACKUP_DIRECTORY`, dan `DB_PHOTO_BACKUP_KEEP_DAYS` sesuai kebutuhan. Arsip foto menyertakan manifest dan mempertahankan path `service-photos/...`. Ekstensi PHP `zip` dan `openssl` diperlukan.

```sh
php artisan database:backup --verify
```

`--verify` memeriksa ukuran minimum dump (`DB_BACKUP_MINIMUM_BYTES`, minimum 1 KiB), integritas SQLite/format dump, serta konsistensi ZIP foto sebelum retensi dibersihkan atau salinan diunggah. SQLite memakai snapshot `VACUUM INTO`; MySQL/MariaDB memerlukan `mysqldump` dan PostgreSQL memerlukan `pg_dump`. Jika binary tidak ada di `PATH`, isi `MYSQLDUMP_BINARY` atau `PG_DUMP_BINARY` dengan path absolut. Driver SQL Server belum didukung. Password MySQL tidak menjadi argumen proses: command membuat file opsi sementara dengan izin terbatas dan menghapusnya setelah `mysqldump` selesai.

Salinan off-site bersifat opsional. Isi `BACKUP_OFFSITE_DISK=s3` untuk S3-compatible atau `BACKUP_OFFSITE_DISK=sftp` untuk SFTP dan konfigurasi disk terkait di `config/filesystems.php`/`.env`:

- S3-compatible memakai `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, dan `AWS_USE_PATH_STYLE_ENDPOINT`. Isi `AWS_ENDPOINT` untuk provider S3-compatible; biarkan kosong untuk AWS S3. Aktifkan `AWS_USE_PATH_STYLE_ENDPOINT=true` hanya bila provider S3-compatible memerlukannya.
- SFTP memakai `SFTP_HOST`, `SFTP_USERNAME`, lalu `SFTP_PASSWORD` atau path `SFTP_PRIVATE_KEY` dan opsional `SFTP_PASSPHRASE`. Tetapkan `SFTP_HOST_FINGERPRINT` (wajib) dan `SFTP_ROOT` ke direktori remote privat yang dapat ditulis agar host server diverifikasi dan akses dibatasi.
- Atur `BACKUP_OFFSITE_PREFIX` untuk prefix objek remote. Paket filesystem S3 dan SFTP sudah menjadi dependency Composer proyek.
- Buat kunci 32 byte acak dengan `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`, lalu simpan hasil Base64 pada `BACKUP_ENCRYPTION_KEY` di secret manager/environment deployment. Jangan commit kunci dan simpan salinan pemulihannya terpisah dari server/backup; arsip tidak dapat didekripsi jika kunci hilang.

Saat off-site aktif, command membuat satu ZIP gabungan database+foto, mengenkripsinya per blok dengan AES-256-GCM, lalu mengunggah hanya file `.zip.enc`. Retensi objek remote tidak dihapus oleh aplikasi; atur lifecycle/retensi pada provider sesuai kebijakan penyimpanan. Jika backup/verifikasi/upload gagal, hasilnya dicatat sebagai `backup_gagal` di `activity_logs`, pesan dikirim ke notifikasi akun owner, dan detail juga ditulis ke log aplikasi. Keberhasilan dicatat sebagai `backup_berhasil`. Tanpa `BACKUP_OFFSITE_DISK`, hanya salinan lokal dibuat.

#### Prosedur pemulihan

1. Hentikan sementara aplikasi, queue worker, dan proses yang menulis ke database/foto. Buat salinan kondisi saat ini sebelum mengganti file atau mengimpor dump.
2. Ambil objek `.zip.enc` dari disk off-site. Dekripsi melalui disk filesystem yang telah dikonfigurasi (ganti disk, key objek, dan path tujuan):

   ```sh
   php artisan database:backup:decrypt "teckfo-backups/full-backup-YYYYMMDD-HHMMSS.zip.enc" "storage/app/private/restore/full-backup.zip" --disk=s3
   ```

   Untuk file terenkripsi lokal, hilangkan opsi `--disk`. Command menolak menimpa file tujuan yang sudah ada dan menghapus hasil parsial bila autentikasi gagal.
3. Periksa isi arsip dengan `unzip -l storage/app/private/restore/full-backup.zip`, lalu ekstrak ke direktori staging privat di luar web root (`unzip -q ... -d /path/staging` di Linux/macOS, atau `Expand-Archive -LiteralPath ... -DestinationPath C:\path\staging` di PowerShell). Paket berisi `database/<dump>`, `service-photos/<snapshot.zip>`, dan manifest. Ekstrak ZIP foto ke staging juga sebelum memasang file.
4. Pulihkan database sesuai driver:
   - **SQLite:** dengan aplikasi tetap berhenti, simpan file database aktif, lalu ganti dengan `database/<dump>.sqlite`. Pulihkan juga owner/permission file agar user aplikasi dapat menulis.
   - **MySQL/MariaDB:** buat database kosong dan impor `database/<dump>.sql` dengan `mysql --defaults-extra-file=/path/privat/restore.cnf NAMA_DATABASE < dump.sql`. `restore.cnf` berisi host/user/password pada bagian `[client]`, harus dapat dibaca hanya oleh akun deployment (POSIX mode `0600`; Windows gunakan ACL NTFS), dan hapus setelah pemulihan. Jangan menaruh password pada argumen CLI.
   - **PostgreSQL:** buat database kosong dan siapkan file password privat dengan permission terbatas. Jalankan `PGPASSFILE=/path/privat/pgpass pg_restore --no-owner --no-privileges --dbname=NAMA_DATABASE dump` (di PowerShell, set `$env:PGPASSFILE` sebelum menjalankan `pg_restore`). Hapus file password setelah pemulihan.
5. Salin isi `service-photos/` dari ZIP foto ke root disk privat `service_photos` (default `storage/app/private`), sehingga path `service-photos/...` tetap sama. Pertahankan permission privat; jangan letakkan foto pada web root.
6. Jalankan `php artisan migrate:status`, verifikasi login, data servis, dan foto melalui aplikasi, kemudian hidupkan kembali queue worker dan aplikasi.

Contoh restore diuji pada **24 November 2026** melalui `php artisan test tests/Feature/DatabaseBackupTest.php`: dump SQLite dari arsip off-site terenkripsi didekripsi, dipulihkan ke database sementara dengan `PRAGMA integrity_check = ok`, dan foto berhasil diekstrak serta dicocokkan. Pengujian memakai filesystem lokal sebagai adapter off-site; koneksi kredensial S3/SFTP produksi tetap perlu diuji pada deployment.

Untuk scheduler lokal, jalankan:

```sh
php artisan schedule:work
```

Di server Linux, pasang cron Laravel setiap menit:

```cron
* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Di Windows/XAMPP, jadwalkan `php artisan schedule:run` setiap menit melalui Task Scheduler dengan *Start in* diarahkan ke root aplikasi. Command backup dapat dipulihkan sesuai driver database: file SQLite dipulihkan saat aplikasi berhenti menulis; dump MySQL/MariaDB diimpor dengan `mysql`; dump PostgreSQL dipulihkan dengan `pg_restore`.

Panel teknisi menyediakan manifest PWA dan service worker ringan. Saat offline, service worker hanya menampilkan halaman offline statis; respons API dan data servis tidak disimpan di cache perangkat. Instalasi PWA memerlukan HTTPS (atau localhost).

## Keamanan, realtime, dan ringkasan laporan

- Semua peran dapat membuka `/password/change` untuk mengganti kata sandi sendiri. Kata sandi lama diverifikasi dan kata sandi baru harus minimal 10 karakter.
- Halaman web memiliki tampilan error berbahasa Indonesia untuk HTTP 403, 404, 419, 429, dan 500. API tetap mengembalikan respons JSON.
- Middleware keamanan mengirim `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dan CSP. CSP mengizinkan Tailwind CDN, Google Fonts, aset same-origin, serta koneksi Reverb `ws:`/`wss:`; origin dev Vite localhost hanya ditambahkan saat `APP_DEBUG=true`.
- Teknisi yang ditetapkan pada servis baru maupun penugasan berikutnya menerima notifikasi database. Event servis untuk staf dikirim melalui channel realtime; dashboard owner/admin/teknisi memperbarui data saat event diterima dan memakai polling 12 detik bila koneksi realtime gagal.
- Laporan owner menampilkan grafik omzet harian/bulanan dan grafik jumlah servis per status serta teknisi.
- Pengingat WhatsApp unit selesai dibatasi maksimal 3 kali per servis dengan jarak minimal 3 hari antarpengingat. Setiap percobaan tetap tercatat di `notification_logs`.
- Broadcast ke channel pelanggan dipisahkan dari event staf dan hanya memuat `status`, `waktu`, serta `catatan` status yang memang ditampilkan pada timeline pelanggan. Nama, nomor telepon, identitas nota/servis, dan data staf tidak masuk ke payload pelanggan.

### Checklist deployment produksi

- [ ] Gunakan HTTPS untuk seluruh aplikasi dan WebSocket (WSS); konfigurasi reverse proxy untuk meneruskan koneksi Reverb.
- [ ] Atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`, kredensial database, rahasia Reverb, dan token WhatsApp khusus deployment.
- [ ] Pastikan guard produksi menolak `APP_DEBUG=true` dan rahasia Reverb placeholder; isi `TRUSTED_PROXIES` hanya dengan IP/CIDR reverse proxy tepercaya.
- [ ] Batasi akses database dan port Reverb; isi `REVERB_ALLOWED_ORIGINS` hanya dengan origin publik yang dipakai.
- [ ] Pasang dependency dan build frontend: `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, dan `npm run build`.
- [ ] Jalankan `php artisan migrate --force`, `php artisan storage:link`, lalu `php artisan optimize`; pastikan disk `service_photos` privat dan foto tidak dapat diakses melalui web root.
- [ ] Pastikan user aplikasi dapat menulis ke `storage` dan `bootstrap/cache`; simpan backup database dan foto di folder privat di luar web root, konfigurasi backup off-site terenkripsi dan uji pemulihannya.
- [ ] Pastikan tabel `payments` sudah dimigrasikan dan status pembayaran dihitung dari ledger sebelum menerima transaksi.
- [ ] Jalankan queue worker dan Reverb secara persisten (misalnya dengan Supervisor); jalankan scheduler setiap menit melalui cron atau Windows Task Scheduler.
- [ ] Jalankan `php artisan queue:restart` setelah deploy, verifikasi status worker/Reverb/scheduler, dan uji pengiriman WhatsApp serta pemulihan backup.
- [ ] Ganti kata sandi seluruh akun awal/demo sebelum memberi akses kepada pengguna. Jangan gunakan data demo pada produksi.

## Notifikasi WhatsApp

Notifikasi servis dikirim melalui queue Laravel. Atur kredensial di `.env` dan `config/services.php`:

- `WHATSAPP_DRIVER=fonnte` (default) dan `FONNTE_TOKEN`
- Alternatif: `WHATSAPP_DRIVER=wablas` dengan `WABLAS_TOKEN`, atau `WHATSAPP_DRIVER=cloud` dengan `WHATSAPP_CLOUD_TOKEN` dan `WHATSAPP_CLOUD_PHONE_NUMBER_ID`
- URL provider dapat dioverride melalui `FONNTE_URL`, `WABLAS_URL`, atau `WHATSAPP_CLOUD_URL`.

Gateway tidak resmi seperti Fonnte dan Wablas berisiko menyebabkan nomor WhatsApp dibatasi atau diblokir. Untuk produksi, utamakan WhatsApp Cloud API resmi.

Jalankan `php artisan queue:work` untuk memproses pesan dan `php artisan schedule:work` (atau cron Laravel Scheduler) untuk pengingat unit selesai yang belum diambil pada pukul 09.00 setiap hari. Owner dapat mengubah template dan mengirim ulang notifikasi gagal melalui `/owner/notifications`. Nomor dinormalisasi otomatis menjadi format `62...`.

## Fitur alur servis

Form pendaftaran mencatat estimasi selesai, prioritas, teknisi, checklist kelengkapan, kondisi fisik, dan foto penerimaan. Teknisi dapat mengubah estimasi dan mengunggah foto setelah servis; foto JPEG/PNG dibatasi 5 MB dan 3000×3000 px, di-decode dan di-encode ulang sebagai JPEG untuk menghapus metadata, lalu disimpan beserta thumbnail pada disk privat `service_photos` di `storage/app/private`. Foto hanya dilayani melalui endpoint berotorisasi untuk staf yang berwenang atau pelanggan dengan akses tracking yang valid; URL `/storage/...` tidak digunakan untuk foto servis.

Jika instalasi lama sudah menyimpan foto pada disk `public`, tinjau terlebih dahulu apa yang akan dipindahkan, kemudian jalankan:

```sh
php artisan service-photos:migrate-private --dry-run
php artisan service-photos:migrate-private
```

Command meng-encode ulang gambar, memperbarui path database, dan menghapus salinan publik setelah path privat berhasil disimpan. Periksa output command dan backup sebelum migrasi. `php artisan storage:link` tetap diperlukan hanya untuk aset lain yang memang publik, seperti logo atau gambar statis.

Batas stok minimum dapat diatur per sparepart. Penggunaan sparepart mengunci stok di dalam transaksi, menolak stok yang tidak cukup, dan menyimpan harga modal/jual sebagai snapshot pada item servis. Garansi mulai berlaku ketika unit diserahkan, sesuai masa garansi servis.

Tanda terima dan nota menggunakan Blade print-CSS, bukan DomPDF: layout dapat dicetak pada thermal 58/80 mm atau disimpan sebagai PDF lewat dialog browser tanpa menambah dependensi server PDF. QR code dihasilkan lokal menggunakan paket `qrcode` dan mengarah ke tautan pelacakan acak per servis.
