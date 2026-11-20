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
4. Jalankan `php artisan serve` dari folder `laravel-app/`.

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

Untuk produksi, gunakan `.env.production.example` sebagai acuan, isi `APP_KEY` dan kredensial database dengan nilai rahasia milik deployment, lalu pastikan `APP_DEBUG=false`. Tidak ada fitur cetak nota; tautan WhatsApp tetap tersedia dari panel admin.
