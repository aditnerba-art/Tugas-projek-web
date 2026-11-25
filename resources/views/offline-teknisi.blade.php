<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1e3f">
    <title>Offline | TECKFO Teknisi</title>
    <style>
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 24px; background: #f8fafc; color: #0b1e3f; font: 16px/1.5 system-ui, sans-serif; text-align: center; }
        main { max-width: 420px; padding: 32px; border: 1px solid #dce4ef; border-top: 4px solid #d4af37; border-radius: 20px; background: #fff; box-shadow: 0 12px 32px #0b1e3f12; }
        img { width: 64px; height: 64px; object-fit: contain; }
        button { margin-top: 12px; padding: 10px 16px; border: 0; border-radius: 10px; background: #0b1e3f; color: #fff; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <img src="/teckfo-logo.png" alt="TECKFO Service">
        <h1>Anda sedang offline</h1>
        <p>Data servis tidak disimpan di perangkat. Sambungkan kembali ke internet untuk membuka panel teknisi.</p>
        <button type="button" onclick="window.location.reload()">Coba lagi</button>
    </main>
</body>
</html>
