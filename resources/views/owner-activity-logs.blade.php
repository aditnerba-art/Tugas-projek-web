<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Aktivitas | TECKFO Service</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8">
<main class="mx-auto max-w-6xl space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-[#0B1E3F]">Log Aktivitas</h1>
            <p class="mt-1 text-sm text-slate-600">Cari, filter, dan urutkan riwayat perubahan panel.</p>
        </div>
        <a class="font-bold text-amber-700" href="/owner.html">Kembali ke dashboard</a>
    </header>
    <form method="get" class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
        <input name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Cari aksi, subjek, pengguna, IP" class="rounded-lg border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
        <input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Filter kode aksi" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="date_from" value="{{ $filters['date_from'] ?? '' }}" type="date" aria-label="Tanggal dari" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="date_to" value="{{ $filters['date_to'] ?? '' }}" type="date" aria-label="Tanggal sampai" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="sort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Waktu</option>
            <option value="aksi" @selected(($filters['sort'] ?? '') === 'aksi')>Aksi</option>
            <option value="subjek" @selected(($filters['sort'] ?? '') === 'subjek')>Subjek</option>
        </select>
        <select name="direction" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Terbaru / Z–A</option>
            <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Terlama / A–Z</option>
        </select>
        <button class="rounded-lg bg-[#0B1E3F] px-4 py-2 text-sm font-bold text-white" type="submit">Terapkan filter</button>
    </form>

    <p class="text-sm text-slate-600" role="status">Menampilkan {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} aktivitas.</p>
    <section class="space-y-3">
        @forelse ($logs as $log)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <div>
                        <strong class="text-[#0B1E3F]">{{ str_replace('_', ' ', ucfirst($log->aksi)) }}</strong>
                        <div class="mt-1 break-all text-xs text-slate-600">Subjek: {{ $log->subjek }}</div>
                    </div>
                    <time class="shrink-0 text-xs text-slate-500">{{ $log->created_at?->format('d-m-Y H:i:s') }}</time>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>Pengguna: {{ $log->user?->username ?? 'Akun dihapus/sistem' }}</span>
                    <span>IP: {{ $log->ip ?? '-' }}</span>
                </div>
                @if ($log->data_lama || $log->data_baru)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-amber-800">Lihat rincian perubahan</summary>
                        <pre class="mt-2 overflow-auto rounded-lg bg-slate-50 p-3 text-xs">{{ json_encode(['lama' => $log->data_lama, 'baru' => $log->data_baru], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Tidak ada aktivitas yang cocok dengan filter.</div>
        @endforelse
    </section>
    <div>{{ $logs->links() }}</div>
</main>
</body>
</html>
