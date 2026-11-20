<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $errorCode }} - {{ $errorTitle }} | TECKFO Service</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #0b1e3f; background: #f3f6fb; }
        body { display: grid; min-height: 100vh; margin: 0; place-items: center; }
        main { width: min(440px, calc(100% - 40px)); padding: 36px; border: 1px solid #dce4ef; border-radius: 20px; background: #fff; box-shadow: 0 16px 48px #0b1e3f12; text-align: center; }
        .code { margin: 0; color: #2563eb; font-size: 64px; font-weight: 800; letter-spacing: -.06em; }
        h1 { margin: 8px 0; font-size: 24px; }
        p { color: #52647e; line-height: 1.6; }
        a { display: inline-block; margin-top: 12px; padding: 11px 18px; border-radius: 10px; background: #0b1e3f; color: #fff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <p class="code">{{ $errorCode }}</p>
    <h1>{{ $errorTitle }}</h1>
    <p>{{ $errorMessage }}</p>
    <a href="/">Kembali ke beranda</a>
</main>
</body>
</html>
