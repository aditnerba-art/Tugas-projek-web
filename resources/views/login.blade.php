<!doctype html>
<html lang="id">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
    (() => {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init = {}) => {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) headers.set('X-CSRF-TOKEN', token);
            return nativeFetch(input, { ...init, headers });
        };
    })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login Staf - TECKFO Service</title>
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/auth.css">
</head>
<body>
<main class="card">
    <div class="mark"><img src="{{ asset('teckfo-logo.png') }}" alt="Logo Teckfo Komputer"> Portal Staf TECKFO</div>
    <h1>Pilih Halaman Masuk</h1>
    <p>Silakan pilih gerbang masuk yang sesuai dengan peran dan tanggung jawab Anda.</p>
    <nav class="role-list" aria-label="Pilih peran">
        <a class="role" href="{{ route('admin.login') }}"><strong>Admin</strong><span>Panel administrasi & kasir →</span></a>
        <a class="role" href="{{ route('teknisi.login') }}"><strong>Teknisi</strong><span>Panel pengerjaan & servis →</span></a>
        <a class="role" href="{{ route('owner.login') }}"><strong>Owner</strong><span>Dashboard eksekutif & staf →</span></a>
    </nav>
    <p class="small">Gunakan username dan kata sandi akun yang telah didaftarkan.</p>
</main>
</body>
</html>
