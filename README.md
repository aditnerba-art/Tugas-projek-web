<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
## TECKFO Service (Laravel)

Aplikasi servis komputer berbasis Laravel 12 dengan portal pelanggan, panel admin/kasir, panel teknisi, dan dashboard owner.

### Menjalankan aplikasi

1. Salin `.env.example` menjadi `.env`, lalu atur koneksi database.
2. Jalankan `php artisan key:generate`.
3. Jalankan `php artisan migrate --seed`.
4. Jalankan `php artisan serve` dari root proyek.

Database SQLite digunakan secara default; koneksi MySQL dapat diatur melalui variabel `DB_*` di `.env`.

### Realtime dan notifikasi

Status servis dicatat di timeline terpusat dan dikirim melalui Laravel Reverb saat `REALTIME_DRIVER=reverb`. Staf memakai private channel sesuai peran. Portal pelanggan menerima nama channel opaque berbasis HMAC hanya dari sesi portal yang sudah valid; endpoint polling tetap memverifikasi sesi dan token akses pelanggan. Jika WebSocket tidak tersambung, frontend otomatis memakai polling JSON dengan ETag setiap 12 detik. Set `REALTIME_DRIVER=polling` untuk menonaktifkan Reverb dan menggunakan polling saja.

Untuk pengembangan lokal, atur nilai Reverb lokal di `.env`, lalu jalankan:

```sh
npm install
npm run build
composer dev
```

`composer dev` menjalankan Laravel, queue worker, Pail, Vite, dan Reverb secara bersamaan. Untuk mencoba polling saja, ubah `REALTIME_DRIVER=polling` dan `VITE_REALTIME_DRIVER=polling`, lalu build ulang asset.

Di produksi, gunakan kredensial rahasia berbeda dari nilai lokal pada `.env.production.example`. Pastikan `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, dan `REVERB_ALLOWED_ORIGINS` sesuai domain publik; terminasi TLS/WSS sebaiknya dilakukan oleh reverse proxy dan port Reverb hanya dibuka secara internal. Jalankan `npm ci && npm run build`, kemudian jalankan proses Reverb dan queue secara persisten.

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
command=php artisan queue:work --sleep=3 --tries=3 --timeout=90
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

Aktifkan dengan `sudo systemctl daemon-reload && sudo systemctl enable --now teckfo-reverb`. Jalankan queue worker sebagai service terpisah juga. Setelah deployment, gunakan `php artisan config:cache` dan `php artisan queue:restart`; verifikasi koneksi WebSocket melalui reverse proxy atau pilih `REALTIME_DRIVER=polling` sebagai fallback.

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

Tabel panel mendukung pencarian, filter yang sesuai dengan datanya, pengurutan, dan pagination server-side. Laporan di panel owner dapat difilter berdasarkan rentang tanggal dan ditampilkan harian atau bulanan. Omzet/laba dikelompokkan menurut **tanggal servis didaftarkan** karena aplikasi belum menyimpan jurnal transaksi dengan tanggal pembayaran tersendiri. Laba kotor dihitung sebagai nilai tagihan servis dikurangi snapshot modal sparepart yang dipakai. Piutang memakai status pembayaran saat ini. CSV dibuat native; tombol PDF membuka halaman print yang dapat disimpan sebagai PDF dari browser.

Backup otomatis dijalankan setiap hari pukul 02:00 dan retensi default 14 hari. Untuk menjalankan atau menguji backup manual:

```sh
php artisan database:backup
```

Backup disimpan di `storage/app/private/backups` (di luar web root). Atur `DB_BACKUP_DIRECTORY` ke lokasi privat yang dapat ditulis oleh user aplikasi dan `DB_BACKUP_KEEP_DAYS` untuk retensi. SQLite memakai snapshot `VACUUM INTO`; MySQL/MariaDB memerlukan `mysqldump` dan PostgreSQL memerlukan `pg_dump`. Jika binari tidak ada di `PATH`, isi `MYSQLDUMP_BINARY` atau `PG_DUMP_BINARY` dengan path absolut. Driver SQL Server belum didukung command backup ini. Simpan salinan terenkripsi di lokasi terpisah/off-site dan uji proses pemulihan secara berkala; jangan mengandalkan salinan di server yang sama sebagai satu-satunya backup.

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

Untuk produksi, gunakan `.env.production.example` sebagai acuan, isi `APP_KEY` dan kredensial database dengan nilai rahasia milik deployment, lalu pastikan `APP_DEBUG=false`. Tidak ada fitur cetak nota; tautan WhatsApp tetap tersedia dari panel admin.
# Notifikasi WhatsApp

Notifikasi servis dikirim melalui queue Laravel. Atur kredensial di `.env` dan `config/services.php`:

- `WHATSAPP_DRIVER=fonnte` (default) dan `FONNTE_TOKEN`
- Alternatif: `WHATSAPP_DRIVER=wablas` dengan `WABLAS_TOKEN`, atau `WHATSAPP_DRIVER=cloud` dengan `WHATSAPP_CLOUD_TOKEN` dan `WHATSAPP_CLOUD_PHONE_NUMBER_ID`
- URL provider dapat dioverride melalui `FONNTE_URL`, `WABLAS_URL`, atau `WHATSAPP_CLOUD_URL`.

Jalankan `php artisan queue:work` untuk memproses pesan dan `php artisan schedule:work` (atau cron Laravel Scheduler) untuk pengingat unit selesai yang belum diambil pada pukul 09.00 setiap hari. Owner dapat mengubah template dan mengirim ulang notifikasi gagal melalui `/owner/notifications`. Nomor dinormalisasi otomatis menjadi format `62...`.

## Fitur alur servis

Form pendaftaran mencatat estimasi selesai, prioritas, teknisi, checklist kelengkapan, kondisi fisik, dan foto penerimaan. Teknisi dapat mengubah estimasi dan mengunggah foto setelah servis; foto JPEG/PNG dibatasi 5 MB dan 3000×3000 px, disimpan dengan nama acak pada disk `public`, serta dibuatkan thumbnail. Jalankan `php artisan storage:link` pada deployment untuk menyajikan foto.

Batas stok minimum dapat diatur per sparepart. Penggunaan sparepart mengunci stok di dalam transaksi, menolak stok yang tidak cukup, dan menyimpan harga modal/jual sebagai snapshot pada item servis. Garansi mulai berlaku ketika unit diserahkan, sesuai masa garansi servis.

Tanda terima dan nota menggunakan Blade print-CSS, bukan DomPDF: layout dapat dicetak pada thermal 58/80 mm atau disimpan sebagai PDF lewat dialog browser tanpa menambah dependensi server PDF. QR code dihasilkan lokal menggunakan paket `qrcode` dan mengarah ke tautan pelacakan acak per servis.
