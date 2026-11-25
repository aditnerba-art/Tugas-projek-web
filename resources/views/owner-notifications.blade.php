<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notifikasi WhatsApp - TECKFO Service</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8">
    <main class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a class="text-sm font-bold text-amber-700" href="{{ route('owner.dashboard') }}">← Kembali ke panel owner</a>
                <h1 class="mt-2 text-2xl font-extrabold text-[#0B1E3F]">Notifikasi WhatsApp</h1>
                <p class="mt-1 text-sm text-slate-600">Ubah template pesan dan tinjau status pengiriman.</p>
            </div>
            <button type="button" onclick="loadLogs()" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold">Muat ulang log</button>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-[#0B1E3F]">Template pesan</h2>
            <p class="mb-4 mt-1 text-xs text-slate-500">Variabel tersedia: {nama}, {kode}, {status}, {link}, {unit}, {biaya}.</p>
            <div id="templateList" class="space-y-4">
                <p class="text-sm text-slate-500">Memuat template…</p>
            </div>
            <p id="templateMessage" class="mt-3 text-sm font-semibold" role="status" aria-live="polite"></p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-bold text-[#0B1E3F]">Riwayat pengiriman</h2>
            <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <input id="logSearch" type="search" placeholder="Cari tujuan, pesan, nota" class="rounded-lg border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
                <select id="logStatus" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua status</option><option value="pending">Antrean</option><option value="sent">Terkirim</option><option value="failed">Gagal</option>
                </select>
                <input id="logDateFrom" type="date" aria-label="Tanggal log dari" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="logDateTo" type="date" aria-label="Tanggal log sampai" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select id="logSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="created_at:desc">Terbaru</option><option value="created_at:asc">Terlama</option><option value="status:asc">Status</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr><th class="p-3">Waktu</th><th class="p-3">Tujuan</th><th class="p-3">Pesan</th><th class="p-3">Status / Error</th><th class="p-3">Aksi</th></tr>
                    </thead>
                    <tbody id="notificationRows" class="divide-y divide-slate-100">
                        <tr><td colspan="5" class="p-5 text-center text-slate-500">Memuat log…</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="logPagination" class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm text-slate-600" aria-live="polite"></div>
            <p id="logMessage" class="mt-3 text-sm font-semibold" role="status" aria-live="polite"></p>
        </section>
    </main>
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const templateList = document.getElementById('templateList');
        const templateMessage = document.getElementById('templateMessage');
        const notificationRows = document.getElementById('notificationRows');
        const logMessage = document.getElementById('logMessage');

        async function loadTemplates() {
            const response = await fetch('/api/owner/notification-templates');
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Template gagal dimuat.');
            templateList.replaceChildren();
            result.data.forEach(template => {
                const form = document.createElement('form');
                form.className = 'space-y-2 rounded-xl border border-slate-200 p-4';
                const label = document.createElement('label');
                label.className = 'block text-sm font-bold text-slate-800';
                label.textContent = template.name;
                const textarea = document.createElement('textarea');
                textarea.name = 'body';
                textarea.required = true;
                textarea.maxLength = 5000;
                textarea.rows = 3;
                textarea.value = template.body;
                textarea.className = 'w-full rounded-lg border border-slate-300 p-3 text-sm';
                const button = document.createElement('button');
                button.type = 'submit';
                button.className = 'rounded-lg bg-[#0B1E3F] px-4 py-2 text-xs font-bold text-white';
                button.textContent = 'Simpan template';
                label.append(textarea);
                form.append(label, button);
                form.addEventListener('submit', event => saveTemplate(event, template.id, textarea.value));
                templateList.append(form);
            });
        }

        async function saveTemplate(event, id, body) {
            event.preventDefault();
            templateMessage.textContent = '';
            try {
                const response = await fetch(`/api/owner/notification-templates/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ body })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Template gagal disimpan.');
                templateMessage.textContent = 'Template berhasil disimpan.';
                templateMessage.className = 'mt-3 text-sm font-semibold text-emerald-700';
            } catch (error) {
                templateMessage.textContent = error.message;
                templateMessage.className = 'mt-3 text-sm font-semibold text-rose-700';
            }
        }

        function renderLogPagination(meta) {
            const container = document.getElementById('logPagination');
            container.replaceChildren();
            if (!meta || !meta.total) {
                container.textContent = 'Tidak ada log untuk filter ini.';
                return;
            }
            const summary = document.createElement('span');
            summary.textContent = `Menampilkan ${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} dari ${meta.total}`;
            const buttons = document.createElement('div');
            buttons.className = 'flex gap-2';
            [['Sebelumnya', meta.current_page - 1], ['Berikutnya', meta.current_page + 1]].forEach(([label, page]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.disabled = label === 'Sebelumnya' ? meta.current_page <= 1 : meta.current_page >= meta.last_page;
                button.className = 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold disabled:opacity-40';
                button.addEventListener('click', () => loadLogs(page));
                buttons.append(button);
            });
            container.append(summary, buttons);
        }

        async function loadLogs(page = 1) {
            logMessage.textContent = '';
            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: '20',
                    sort: document.getElementById('logSort').value.split(':')[0],
                    direction: document.getElementById('logSort').value.split(':')[1],
                });
                const search = document.getElementById('logSearch').value.trim();
                const status = document.getElementById('logStatus').value;
                const dateFrom = document.getElementById('logDateFrom').value;
                const dateTo = document.getElementById('logDateTo').value;
                if (search) params.set('search', search);
                if (status) params.set('status', status);
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);
                const response = await fetch(`/api/owner/notification-logs?${params}`);
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Log gagal dimuat.');
                notificationRows.replaceChildren();
                const logs = result.data || [];
                if (!logs.length) {
                    const row = notificationRows.insertRow();
                    const cell = row.insertCell();
                    cell.colSpan = 5;
                    cell.className = 'p-5 text-center text-slate-500';
                    cell.textContent = 'Belum ada riwayat notifikasi.';
                    renderLogPagination(result.meta);
                    return;
                }
                logs.forEach(log => {
                    const row = notificationRows.insertRow();
                    [new Date(log.created_at).toLocaleString('id-ID'), log.recipient, log.message,
                        `${log.status}${log.error ? ` — ${log.error}` : ''}`].forEach(value => {
                        const cell = row.insertCell();
                        cell.className = 'max-w-sm whitespace-pre-wrap break-words p-3 align-top';
                        cell.textContent = value || '-';
                    });
                    const action = row.insertCell();
                    action.className = 'p-3 align-top';
                    if (log.status === 'failed') {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'whitespace-nowrap rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-slate-950';
                        button.textContent = 'Kirim ulang';
                        button.addEventListener('click', () => resend(log.id));
                        action.append(button);
                    } else {
                        action.textContent = '-';
                    }
                });
                renderLogPagination(result.meta);
            } catch (error) {
                logMessage.textContent = error.message;
                logMessage.className = 'mt-3 text-sm font-semibold text-rose-700';
                notificationRows.innerHTML = '<tr><td colspan="5" class="p-5 text-center text-rose-700">Log gagal dimuat. Periksa koneksi lalu coba lagi.</td></tr>';
            }
        }

        async function resend(id) {
            logMessage.textContent = '';
            try {
                const response = await fetch(`/api/owner/notification-logs/${id}/resend`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf }
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Pesan gagal dimasukkan ke antrean.');
                logMessage.textContent = result.message;
                logMessage.className = 'mt-3 text-sm font-semibold text-emerald-700';
                await loadLogs();
            } catch (error) {
                logMessage.textContent = error.message;
                logMessage.className = 'mt-3 text-sm font-semibold text-rose-700';
            }
        }

        let logSearchTimer;
        document.getElementById('logSearch').addEventListener('input', () => {
            clearTimeout(logSearchTimer);
            logSearchTimer = setTimeout(() => loadLogs(1), 250);
        });
        ['logStatus', 'logDateFrom', 'logDateTo', 'logSort'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => loadLogs(1));
        });
        Promise.all([loadTemplates(), loadLogs()]).catch(error => {
            templateMessage.textContent = error.message;
            templateMessage.className = 'mt-3 text-sm font-semibold text-rose-700';
        });
    </script>
</body>
</html>
