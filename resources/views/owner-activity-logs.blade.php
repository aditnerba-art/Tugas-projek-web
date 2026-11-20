<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Aktivitas | TECKFO Service</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #f3f6fb; color: #0b1e3f; font: 14px/1.5 system-ui, sans-serif; }
        main { max-width: 1100px; margin: auto; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        a { color: #1d4ed8; font-weight: 700; }
        .log { margin: 12px 0; padding: 18px; border: 1px solid #dce4ef; border-radius: 12px; background: #fff; }
        .meta { display: flex; flex-wrap: wrap; gap: 12px; color: #586a83; font-size: 12px; }
        pre { overflow: auto; padding: 12px; border-radius: 8px; background: #f7f9fc; white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <header>
        <div><h1>Log Aktivitas</h1><p>Menampilkan hingga 200 aktivitas terbaru.</p></div>
        <a href="/owner.html">Kembali ke dashboard</a>
    </header>
    @forelse ($logs as $log)
        <article class="log">
            <strong>{{ str_replace('_', ' ', ucfirst($log->aksi)) }}</strong>
            <div class="meta">
                <span>Pengguna: {{ $log->user?->username ?? 'Akun dihapus/sistem' }}</span>
                <span>Subjek: {{ $log->subjek }}</span>
                <span>IP: {{ $log->ip ?? '-' }}</span>
                <time>{{ $log->created_at?->format('d-m-Y H:i:s') }}</time>
            </div>
            @if ($log->data_lama || $log->data_baru)
                <pre>{{ json_encode(['lama' => $log->data_lama, 'baru' => $log->data_baru], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </article>
    @empty
        <p>Belum ada aktivitas tercatat.</p>
    @endforelse
</main>
</body>
</html>
