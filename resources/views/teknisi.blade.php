<!DOCTYPE html>
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0B1E3F">
    <title>Panel Teknisi - TECKFO Service</title>
    <link rel="manifest" href="/manifest.webmanifest">
    @include('partials.realtime-scripts')
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>

<body data-theme="dashboard" class="bg-slate-50 text-slate-900 min-h-screen flex flex-col font-sans">
    <div class="app-topbar">
        <a href="{{ route('teknisi.dashboard') }}" class="app-nav-link">🛠️ Panel Teknisi</a>
    </div>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#0B1E3F] text-amber-400 mb-2 border border-amber-400/40">
                        ⚡ Workbench & Service Log
                    </span>
                    <h1 class="text-2xl font-extrabold text-[#0B1E3F] tracking-tight">Panel Kerja Teknisi</h1>
                    <p class="text-xs text-slate-500 mt-1">Tinjau deadline, mulai pekerjaan, dan catat progres servis yang ditugaskan.</p>
                </div>
                <button onclick="initData()"
                    class="inline-flex items-center gap-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold px-4 py-2.5 rounded-xl border border-slate-300 transition shadow-sm">
                    🔄 Refresh Data
                </button>
            </div>

            <!-- Form Update Status -->
            <section class="overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white shadow-sm" aria-labelledby="jobListTitle">
                <div class="space-y-4 p-5 sm:p-7">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 id="jobListTitle" class="text-lg font-extrabold text-[#0B1E3F]">Daftar pekerjaan</h2>
                            <p class="mt-1 text-xs text-slate-500">Pekerjaan yang ditugaskan kepada Anda, diurutkan menurut deadline dan prioritas.</p>
                        </div>
                        <label class="w-full sm:max-w-xs">
                            <span class="sr-only">Cari pekerjaan</span>
                            <input id="serviceSearchFilter" type="search" placeholder="Cari nota, pelanggan, unit"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter status pekerjaan">
                        <button type="button" class="work-filter-tab min-h-11 rounded-lg border border-[#0B1E3F] bg-[#0B1E3F] px-4 py-2 text-sm font-bold text-white" data-work-filter="all" role="tab" aria-selected="true">
                            Semua <span id="workCountAll" class="ml-1 rounded-full bg-white/20 px-2 py-0.5">0</span>
                        </button>
                        <button type="button" class="work-filter-tab min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700" data-work-filter="not_started" role="tab" aria-selected="false">
                            Belum dikerjakan <span id="workCountNotStarted" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5">0</span>
                        </button>
                        <button type="button" class="work-filter-tab min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700" data-work-filter="in_progress" role="tab" aria-selected="false">
                            Sedang dikerjakan <span id="workCountInProgress" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5">0</span>
                        </button>
                        <button type="button" class="work-filter-tab min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700" data-work-filter="completed" role="tab" aria-selected="false">
                            Selesai <span id="workCountCompleted" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5">0</span>
                        </button>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[980px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th scope="col" class="px-3 py-3">Tanggal masuk</th>
                                    <th scope="col" class="px-3 py-3">Pelanggan / unit / keluhan</th>
                                    <th scope="col" class="px-3 py-3">Prioritas</th>
                                    <th scope="col" class="px-3 py-3">Deadline</th>
                                    <th scope="col" class="px-3 py-3">Sisa hari</th>
                                    <th scope="col" class="px-3 py-3">Status</th>
                                    <th scope="col" class="px-3 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="serviceListBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                    <p id="serviceListMessage" class="text-sm text-slate-600" aria-live="polite">Memuat pekerjaan…</p>
                    <div id="serviceListPagination" class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600" aria-live="polite"></div>
                </div>
            </section>

            <section id="serviceDetailWrap" class="hidden space-y-6 rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="serviceDetailTitle">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="serviceDetailTitle" class="text-lg font-extrabold text-[#0B1E3F]">Detail dan pembaruan pekerjaan</h2>
                    <button type="button" onclick="closeServiceDetail()" class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Tutup detail</button>
                </div>
                <select id="service_id" class="hidden" aria-hidden="true"></select>

                <!-- Info Banner Unit yang Dipilih -->
                <div id="unitInfoBanner" class="hidden p-5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pb-3 border-b border-slate-200">
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200">
                            <span class="text-slate-500 block text-[11px]">Nama Pelanggan:</span>
                            <span id="banner_pelanggan" class="font-bold text-slate-900 text-sm"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200">
                            <span class="text-slate-500 block text-[11px]">Unit Perangkat:</span>
                            <span id="banner_unit" class="font-bold text-slate-900 text-sm"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200">
                            <span class="text-slate-500 block text-[11px]">Teknisi Penanggung Jawab (PIC):</span>
                            <span id="banner_teknisi" class="font-bold text-[#0B1E3F]"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200">
                            <span class="text-slate-500 block text-[11px]">Status Saat Ini:</span>
                            <span id="banner_status" class="font-bold text-amber-700 uppercase"></span>
                        </div>
                    </div>
                    <div class="p-3 bg-rose-50/70 rounded-lg border border-rose-200 text-slate-800">
                        <span class="font-bold text-rose-800 block text-xs mb-0.5">Keluhan Awal:</span>
                        <span id="banner_keluhan" class="text-slate-700 font-medium"></span>
                    </div>
                    <!-- List Onderdil Terpasang -->
                    <div id="banner_onderdil_wrap" class="pt-2 border-t border-slate-200">
                        <span class="font-bold text-slate-700 block mb-1.5">Onderdil Terpasang Sebelumnya:</span>
                        <div id="banner_onderdil_list" class="space-y-1.5"></div>
                    </div>
                </div>

                <section id="statusTimelineWrap" class="hidden rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="statusTimelineTitle">
                    <h2 id="statusTimelineTitle" class="mb-3 text-sm font-extrabold text-[#0B1E3F]">Riwayat Status Servis</h2>
                    <ol id="statusTimeline" class="space-y-3"></ol>
                </section>

                <form id="formUpdate" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Status Baru Servis <span class="text-rose-500">*</span></label>
                            <select id="status_baru" required
                                class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                                <option value="diperiksa">🔍 Diperiksa (Pengecekan Awal)</option>
                                <option value="menunggu_persetujuan">⏳ Menunggu Persetujuan Pelanggan</option>
                                <option value="dikerjakan">⚙️ Dikerjakan</option>
                                <option value="menunggu_sparepart">📦 Menunggu Sparepart</option>
                                <option value="selesai">✅ Selesai (Siap Diambil)</option>
                                <option value="batal">❌ Batal / Tidak Bisa Diperbaiki</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tarif Jasa (ditetapkan Owner)</label>
                            <select id="service_rate_id" onchange="calculateEstimatedTotal()"
                                class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 font-bold">
                                <option value="">Tarif jasa belum dipilih</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-[#0B1E3F] mb-1.5">Estimasi Total Biaya (Rp)</label>
                            <input type="number" id="total_biaya" placeholder="0" min="0" readonly
                                class="w-full py-2.5 px-3.5 bg-slate-50 border-2 border-amber-400/80 rounded-xl text-sm text-emerald-700 font-black focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                            <span class="text-[11px] text-slate-500 font-medium">Dihitung dari tarif Owner dan sparepart</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Estimasi Selesai</label>
                            <input type="date" id="estimated_completion_at" disabled class="w-full rounded-xl border border-slate-300 bg-slate-100 px-3 py-2.5 text-sm">
                            <span class="text-[11px] text-slate-500">Tanggal baru perlu persetujuan pelanggan.</span>
                        </div>
                        <div>
                            <label for="warranty_preset_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Preset Garansi</label>
                            <select id="warranty_preset_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">Tanpa garansi</option>
                            </select>
                            <span class="text-[11px] text-slate-500">Durasi garansi dikelola Owner.</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Foto setelah servis (maks. 5 foto)</label>
                            <input type="file" id="completion_photos" accept="image/jpeg,image/png" multiple class="w-full rounded-xl border border-slate-300 p-2 text-sm">
                        </div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                        <button id="openEstimateChange" type="button" class="min-h-11 rounded-lg border border-amber-400 bg-white px-4 py-2 text-sm font-bold text-[#0B1E3F]">
                            Perubahan estimasi
                        </button>
                        <form id="estimateChangeForm" class="mt-4 hidden space-y-3">
                            <div>
                                <label for="estimateChangeReason" class="mb-1 block text-xs font-bold text-slate-700">Alasan perubahan <span class="text-rose-600">*</span></label>
                                <textarea id="estimateChangeReason" required minlength="5" maxlength="1000" rows="2" placeholder="Contoh: Komponen pengganti belum tersedia"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                            </div>
                            <div class="max-w-xs">
                                <label for="estimateChangeDays" class="mb-1 block text-xs font-bold text-slate-700">Tambahan waktu (hari kalender) <span class="text-rose-600">*</span></label>
                                <input id="estimateChangeDays" type="number" min="1" max="180" required value="1"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            </div>
                            <p id="estimateChangeFeedback" class="text-sm" role="status" aria-live="polite"></p>
                            <button type="submit" class="min-h-11 rounded-lg bg-[#0B1E3F] px-4 py-2 text-sm font-bold text-white">Kirim untuk persetujuan</button>
                        </form>
                    </div>

                    <!-- Input Pemasangan Sparepart Baru -->
                    <div class="p-5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-[#0B1E3F]">Pasang Onderdil / Sparepart Baru (Otomatis Potong Stok)</label>
                            <span id="sparepart_info" class="text-xs text-amber-800 font-bold"></span>
                        </div>
                        <div id="sparepartItemsList" class="space-y-3"></div>
                        <button id="addSparepartItem" type="button" class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-[#0B1E3F]">
                            + Tambah sparepart
                        </button>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Catatan Hasil Diagnosa / Pengerjaan <span class="text-rose-500">*</span></label>
                        <textarea id="catatan_teknis" required rows="3"
                            placeholder="Contoh: Penggantian thermal paste & RAM 8GB DDR4 selesai. Suhu CPU stabil 45°C, lolos uji stress test normal."
                            class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 text-slate-900"></textarea>
                    </div>

                    <button type="button" onclick="submitUpdate()"
                        class="w-full bg-[#0B1E3F] hover:bg-[#132F63] text-white font-extrabold py-3.5 px-6 rounded-xl transition text-sm border border-amber-400/80 shadow-md flex items-center justify-center gap-2">
                        <span>⚡</span> Simpan Progres Pengerjaan
                    </button>
                </form>
            </section>
        </div>
    </main>

    <script>
        let servicesCache = [];
        let sparepartsCache = [];
        let serviceRatesCache = [];
        let pendingServicePage = 1;
        let currentWorkFilter = 'all';

        function renderStatusTimeline(logs) {
            const wrap = document.getElementById('statusTimelineWrap');
            const timeline = document.getElementById('statusTimeline');
            timeline.replaceChildren();
            wrap.classList.remove('hidden');

            if (!logs || logs.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'text-xs italic text-slate-500';
                empty.textContent = 'Belum ada riwayat status.';
                timeline.appendChild(empty);
                return;
            }

            logs.forEach(log => {
                const item = document.createElement('li');
                item.className = 'border-l-2 border-amber-400 pl-3';
                const status = document.createElement('strong');
                status.className = 'block text-xs uppercase text-[#0B1E3F]';
                status.textContent = (log.status_baru || 'Update').replaceAll('_', ' ');
                const metadata = document.createElement('time');
                metadata.className = 'block text-[10px] text-slate-500';
                metadata.dateTime = log.created_at || '';
                metadata.textContent = `${log.user || 'Pelanggan / sistem'}${log.user_role ? ` · ${log.user_role}` : ''} · ${log.created_at ? new Date(log.created_at).toLocaleString('id-ID') : '-'}`;
                const note = document.createElement('p');
                note.className = 'mt-1 whitespace-pre-wrap text-xs text-slate-700';
                note.textContent = log.catatan || 'Tidak ada catatan.';
                item.append(status, metadata, note);
                timeline.appendChild(item);
            });
        }

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        // Ambil daftar unit servis yang belum diambil pelanggan
        function renderServicePagination(meta) {
            const container = document.getElementById('serviceListPagination');
            container.replaceChildren();
            if (!meta || !meta.total) {
                container.textContent = 'Tidak ada servis yang cocok dengan filter.';
                return;
            }
            const summary = document.createElement('span');
            summary.textContent = `Halaman ${meta.current_page} dari ${meta.last_page} · ${meta.total} servis`;
            const buttons = document.createElement('div');
            buttons.className = 'flex gap-2';
            [['Sebelumnya', meta.current_page - 1], ['Berikutnya', meta.current_page + 1]].forEach(([label, page]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.disabled = label === 'Sebelumnya' ? meta.current_page <= 1 : meta.current_page >= meta.last_page;
                button.className = 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-bold disabled:opacity-40';
                button.addEventListener('click', () => loadPendingServices(page));
                buttons.append(button);
            });
            container.append(summary, buttons);
        }

        function formatWorkDate(value) {
            if (!value) return 'Belum ditentukan';
            const date = new Date(value);
            return Number.isNaN(date.getTime())
                ? 'Belum ditentukan'
                : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(date);
        }

        function remainingDays(deadline) {
            if (!deadline) return null;
            const parts = String(deadline).slice(0, 10).split('-').map(Number);
            if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
            const todayParts = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit',
            }).formatToParts(new Date());
            const today = Object.fromEntries(todayParts.map(part => [part.type, part.value]));
            return Math.round((Date.UTC(parts[0], parts[1] - 1, parts[2])
                - Date.UTC(Number(today.year), Number(today.month) - 1, Number(today.day))) / 86400000);
        }

        function appendCell(row, value, className = 'px-3 py-3 align-top') {
            const cell = document.createElement('td');
            cell.className = className;
            cell.textContent = value;
            row.appendChild(cell);
            return cell;
        }

        function renderServiceRows() {
            const body = document.getElementById('serviceListBody');
            const message = document.getElementById('serviceListMessage');
            const select = document.getElementById('service_id');
            body.replaceChildren();
            select.replaceChildren(new Option('-- Pilih pekerjaan --', ''));

            servicesCache.forEach(service => {
                select.add(new Option(`[${service.no_nota}] ${service.nama_pelanggan} - ${service.merek_tipe}`, service.id));
                const row = document.createElement('tr');
                const due = remainingDays(service.estimated_completion_at);
                appendCell(row, formatWorkDate(service.created_at));

                const customerCell = document.createElement('td');
                customerCell.className = 'px-3 py-3 align-top';
                const customer = document.createElement('strong');
                customer.className = 'block text-slate-900';
                customer.textContent = service.nama_pelanggan || 'Pelanggan';
                const unit = document.createElement('span');
                unit.className = 'mt-1 block text-xs font-semibold text-slate-700';
                unit.textContent = `${service.jenis_unit || 'Unit'} · ${service.merek_tipe || '-'}`;
                const complaint = document.createElement('span');
                complaint.className = 'mt-1 block max-w-xs whitespace-normal text-xs text-slate-500';
                complaint.textContent = service.keluhan || 'Keluhan belum dicatat';
                customerCell.append(customer, unit, complaint);
                row.appendChild(customerCell);

                const priorityNames = { urgent: 'Mendesak', high: 'Tinggi', normal: 'Normal' };
                const priorityCell = appendCell(row, priorityNames[service.priority] || 'Normal');
                priorityCell.className += service.priority === 'urgent'
                    ? ' font-extrabold text-rose-700'
                    : service.priority === 'high' ? ' font-bold text-amber-700' : ' text-slate-600';
                appendCell(row, formatWorkDate(service.estimated_completion_at));

                const remainingCell = document.createElement('td');
                remainingCell.className = 'px-3 py-3 align-top';
                const remaining = document.createElement('span');
                remaining.className = 'inline-flex min-h-8 items-center rounded-full px-2.5 text-xs font-bold';
                if (due === null) {
                    remaining.textContent = 'Belum ada deadline';
                    remaining.className += ' bg-slate-100 text-slate-600';
                } else if (due < 0) {
                    remaining.textContent = `Terlambat ${Math.abs(due)} hari`;
                    remaining.className += ' bg-rose-100 text-rose-800';
                } else if (due <= 2) {
                    remaining.textContent = due === 0 ? 'Deadline hari ini' : `Sisa ${due} hari · Mendesak`;
                    remaining.className += ' bg-amber-100 text-amber-900';
                } else {
                    remaining.textContent = `Sisa ${due} hari`;
                    remaining.className += ' bg-emerald-100 text-emerald-800';
                }
                remainingCell.appendChild(remaining);
                row.appendChild(remainingCell);

                const statusNames = {
                    masuk: 'Belum dikerjakan', diperiksa: 'Diperiksa',
                    menunggu_persetujuan: 'Menunggu persetujuan', dikerjakan: 'Sedang dikerjakan',
                    menunggu_sparepart: 'Menunggu sparepart', selesai: 'Selesai',
                    diambil: 'Sudah diambil', batal: 'Dibatalkan',
                };
                const statusCell = appendCell(row, statusNames[service.status] || service.status || '-');
                statusCell.className += service.status === 'menunggu_persetujuan'
                    ? ' font-bold text-amber-800'
                    : ['selesai', 'diambil'].includes(service.status) ? ' font-bold text-emerald-700' : ' text-slate-700';

                const actionCell = document.createElement('td');
                actionCell.className = 'px-3 py-3 align-top';
                const actions = document.createElement('div');
                actions.className = 'flex flex-col gap-2';
                if (['masuk', 'diperiksa'].includes(service.status)) {
                    const startButton = document.createElement('button');
                    startButton.type = 'button';
                    startButton.textContent = 'Mulai';
                    startButton.className = 'min-h-11 rounded-lg bg-[#0B1E3F] px-3 py-2 text-xs font-extrabold text-white hover:bg-[#132F63]';
                    startButton.addEventListener('click', () => startService(service.id, startButton));
                    actions.appendChild(startButton);
                }
                const detailButton = document.createElement('button');
                detailButton.type = 'button';
                detailButton.textContent = 'Detail';
                detailButton.className = 'min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50';
                detailButton.addEventListener('click', () => openServiceDetail(service.id));
                actions.appendChild(detailButton);
                actionCell.appendChild(actions);
                row.appendChild(actionCell);
                body.appendChild(row);
            });

            message.textContent = servicesCache.length ? `${servicesCache.length} pekerjaan ditampilkan.` : 'Tidak ada pekerjaan pada filter ini.';
        }

        function updateWorkCounts(counts = {}) {
            document.getElementById('workCountAll').textContent = counts.all || 0;
            document.getElementById('workCountNotStarted').textContent = counts.not_started || 0;
            document.getElementById('workCountInProgress').textContent = counts.in_progress || 0;
            document.getElementById('workCountCompleted').textContent = counts.completed || 0;
        }

        function setWorkFilter(filter) {
            currentWorkFilter = filter;
            document.querySelectorAll('.work-filter-tab').forEach(button => {
                const active = button.dataset.workFilter === filter;
                button.setAttribute('aria-selected', String(active));
                button.className = `work-filter-tab min-h-11 rounded-lg border px-4 py-2 text-sm font-bold ${
                    active ? 'border-[#0B1E3F] bg-[#0B1E3F] text-white' : 'border-slate-300 bg-white text-slate-700'
                }`;
            });
        }

        function openServiceDetail(serviceId) {
            const select = document.getElementById('service_id');
            select.value = String(serviceId);
            document.getElementById('serviceDetailWrap').classList.remove('hidden');
            onSelectService();
            document.getElementById('serviceDetailTitle').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeServiceDetail() {
            document.getElementById('service_id').value = '';
            document.getElementById('unitInfoBanner').classList.add('hidden');
            document.getElementById('statusTimelineWrap').classList.add('hidden');
            document.getElementById('serviceDetailWrap').classList.add('hidden');
        }

        async function startService(serviceId, button) {
            button.disabled = true;
            try {
                const response = await fetch('/api/admin/service/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_id: serviceId,
                        status_baru: 'dikerjakan',
                        catatan_teknis: 'Pekerjaan dimulai oleh teknisi.',
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Pekerjaan gagal dimulai.');
                await loadPendingServices(1);
            } catch (error) {
                alert(error.message || 'Pekerjaan gagal dimulai. Coba lagi.');
                button.disabled = false;
            }
        }

        async function loadPendingServices(page = 1) {
            pendingServicePage = page;
            const select = document.getElementById('service_id');
            document.getElementById('serviceListMessage').textContent = 'Memuat pekerjaan…';
            try {
                const params = new URLSearchParams({
                    page: String(pendingServicePage),
                    per_page: '20',
                    work_filter: currentWorkFilter,
                });
                const search = document.getElementById('serviceSearchFilter').value.trim();
                if (search) params.set('search', search);
                const res = await fetch(`/api/admin/services-active?${params}`);
                const result = await res.json();
                if (!res.ok || result.status !== 'success') throw new Error(result.message || 'Daftar servis gagal dimuat.');

                servicesCache = result.data;
                renderServiceRows();
                updateWorkCounts(result.meta.work_counts);
                renderServicePagination(result.meta);
            } catch (err) {
                servicesCache = [];
                renderServiceRows();
                document.getElementById('serviceListMessage').textContent = err.message || 'Daftar pekerjaan gagal dimuat. Periksa koneksi lalu coba lagi.';
                document.getElementById('serviceListPagination').replaceChildren();
                updateWorkCounts();
            }
        }

        // Ambil daftar sparepart
        async function loadSpareparts() {
            try {
                const res = await fetch('/api/spareparts?per_page=100');
                const result = await res.json();
                if (!res.ok || result.status !== 'success') throw new Error(result.message || 'Daftar sparepart gagal dimuat.');

                sparepartsCache = result.data;
                const selects = document.querySelectorAll('#sparepartItemsList select');
                if (!selects.length) addSparepartRow();
                document.querySelectorAll('#sparepartItemsList select').forEach(select => {
                    updateSparepartSelect(select, select.value);
                });
                document.getElementById('sparepart_info').textContent = '';
            } catch (err) {
                document.getElementById('sparepart_info').textContent = err.message || 'Sparepart gagal dimuat.';
            }
        }

        async function loadServiceRates() {
                const select = document.getElementById('service_rate_id');
                try {
                    const response = await fetch('/api/service-rates');
                    const result = await response.json();
                    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Tarif jasa gagal dimuat.');
                    serviceRatesCache = result.data;
                    select.replaceChildren(new Option('Gunakan tarif servis saat ini', ''));
                    serviceRatesCache.forEach(rate => {
                        const option = new Option(`${rate.name} · Rp ${Number(rate.amount).toLocaleString('id-ID')}`, rate.id);
                        option.dataset.amount = rate.amount;
                        select.add(option);
                    });
                } catch (error) {
                    select.replaceChildren(new Option('Tarif jasa gagal dimuat', ''));
                    document.getElementById('sparepart_info').textContent = error.message || 'Tarif jasa gagal dimuat.';
                }
        }

        async function loadWarrantyPresets() {
                    const select = document.getElementById('warranty_preset_id');
                    try {
                        const response = await fetch('/api/warranty-presets');
                        const result = await response.json();
                        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Preset garansi gagal dimuat.');
                        select.replaceChildren(new Option('Tanpa garansi', ''));
                        result.data.forEach(preset => {
                            select.add(new Option(`${preset.name} · ${preset.days} hari`, preset.id));
                        });
                    } catch (error) {
                        select.replaceChildren(new Option('Preset garansi gagal dimuat', ''));
                        document.getElementById('sparepart_info').textContent = error.message || 'Preset garansi gagal dimuat.';
            }
        }

        // Saat unit servis dipilih dari dropdown
        async function onSelectService() {
            const srvId = document.getElementById('service_id').value;
            const banner = document.getElementById('unitInfoBanner');

            if (!srvId) {
                banner.classList.add('hidden');
                document.getElementById('service_rate_id').value = '';
                document.getElementById('warranty_preset_id').value = '';
                document.getElementById('total_biaya').value = '';
                return;
            }

            const srv = servicesCache.find(s => s.id == srvId);
            if (srv) {
                document.getElementById('banner_pelanggan').innerText = srv.nama_pelanggan || '-';
                document.getElementById('banner_unit').innerText = `${srv.jenis_unit || ''} - ${srv.merek_tipe || ''}`;
                document.getElementById('banner_teknisi').innerText = srv.nama_teknisi || 'Belum Ditugaskan';
                document.getElementById('banner_status').innerText = (srv.status || '').replace('_', ' ');
                document.getElementById('banner_keluhan').innerText = srv.keluhan || '-';
                document.getElementById('service_rate_id').value = srv.service_rate_id || '';
                document.getElementById('total_biaya').value = srv.total_biaya || 0;
                document.getElementById('estimated_completion_at').value = srv.estimated_completion_at
                    ? String(srv.estimated_completion_at).slice(0, 10)
                    : '';
                document.getElementById('warranty_preset_id').value = srv.warranty_preset_id || '';
                document.getElementById('status_baru').value = srv.status || 'diperiksa';
                banner.classList.remove('hidden');
                calculateEstimatedTotal();

                // Ambil info onderdil yang sudah dipasang lewat endpoint nota
                try {
                    const resNota = await fetch(`/api/admin/nota/${srv.no_nota}`);
                    const notaRes = await resNota.json();
                    const listWrap = document.getElementById('banner_onderdil_list');
                    listWrap.replaceChildren();

                    if (notaRes.status === 'success' && notaRes.data.onderdil && notaRes.data.onderdil.length > 0) {
                        notaRes.data.onderdil.forEach(item => {
                            const row = document.createElement('div');
                            row.className = 'flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs';
                            const name = document.createElement('span');
                            name.className = 'font-semibold text-slate-800';
                            name.textContent = `• ${item.nama_barang} (x${item.jumlah})`;
                            const subtotal = document.createElement('span');
                            subtotal.className = 'font-bold text-emerald-700';
                            subtotal.textContent = `Rp ${Number(item.subtotal).toLocaleString('id-ID')}`;
                            const state = document.createElement('span');
                            state.className = 'rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-700';
                            state.textContent = item.approval_status === 'pending_approval' ? 'Menunggu persetujuan' : 'Disetujui';
                            row.append(name, subtotal, state);
                            if (item.approval_status === 'pending_approval' && ['menunggu_persetujuan', 'menunggu persetujuan'].includes(srv.status)) {
                                const cancel = document.createElement('button');
                                cancel.type = 'button';
                                cancel.className = 'min-h-9 rounded-lg border border-rose-200 px-3 py-1 font-bold text-rose-700';
                                cancel.textContent = 'Batalkan item';
                                cancel.addEventListener('click', () => cancelPendingSparepart(item.id, cancel));
                                row.appendChild(cancel);
                            }
                            listWrap.appendChild(row);
                        });
                    } else {
                        const empty = document.createElement('span');
                        empty.className = 'text-slate-500 italic';
                        empty.textContent = 'Belum ada onderdil terpasang.';
                        listWrap.appendChild(empty);
                    }
                    renderStatusTimeline(notaRes.data?.status_timeline || []);
                } catch (e) {
                    console.error(e);
                    renderStatusTimeline([]);
                }
            }
        }

        function updateSparepartSelect(select, selectedValue = '') {
            select.replaceChildren(new Option('-- Pilih sparepart --', ''));
            sparepartsCache.forEach(item => {
                const option = new Option(
                    `${item.stok_rendah ? '⚠️ Stok rendah · ' : ''}${item.nama_barang} (Stok: ${item.stok}) · Rp ${Number(item.harga_jual).toLocaleString('id-ID')}`,
                    item.id,
                );
                option.dataset.stok = item.stok;
                option.dataset.minimum = item.stok_minimum;
                option.dataset.harga = item.harga_jual;
                select.add(option);
            });
            select.value = selectedValue;
        }

        function addSparepartRow() {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_120px_auto]';
            const select = document.createElement('select');
            select.className = 'w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm';
            select.setAttribute('aria-label', 'Pilih sparepart');
            updateSparepartSelect(select);
            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.min = '1';
            quantity.placeholder = 'Qty';
            quantity.className = 'w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm';
            quantity.setAttribute('aria-label', 'Jumlah sparepart');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'min-h-11 rounded-lg border border-rose-200 px-3 py-2 text-sm font-bold text-rose-700';
            remove.textContent = 'Hapus';
            select.addEventListener('change', () => {
                if (select.value && !quantity.value) quantity.value = '1';
                calculateEstimatedTotal();
            });
            quantity.addEventListener('input', calculateEstimatedTotal);
            remove.addEventListener('click', () => {
                const rows = document.querySelectorAll('#sparepartItemsList > div');
                if (rows.length === 1) {
                    select.value = '';
                    quantity.value = '';
                } else {
                    row.remove();
                }
                calculateEstimatedTotal();
            });
            row.append(select, quantity, remove);
            document.getElementById('sparepartItemsList').appendChild(row);
        }

        async function cancelPendingSparepart(itemId, button) {
            if (!window.confirm('Batalkan sparepart ini dan kembalikan stok?')) return;
            button.disabled = true;
            try {
                const response = await fetch(`/api/admin/service/items/${itemId}/cancel`, { method: 'POST' });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Sparepart gagal dibatalkan.');
                await initData();
            } catch (error) {
                button.disabled = false;
                alert(error.message || 'Sparepart gagal dibatalkan.');
            }
        }

        function calculateEstimatedTotal() {
            const srvId = document.getElementById('service_id').value;
            const currentSrv = servicesCache.find(s => s.id == srvId);
            const baseTotalBiaya = currentSrv ? parseFloat(currentSrv.total_biaya || 0) : 0;
            const currentJasa = currentSrv ? parseFloat(currentSrv.biaya_jasa || 0) : 0;

            const rateSelect = document.getElementById('service_rate_id');
            const selectedRate = serviceRatesCache.find(rate => String(rate.id) === rateSelect.value);
            const inputJasa = selectedRate ? Number(selectedRate.amount) : Number(currentSrv?.biaya_jasa || 0);
            
            let sparepartSubtotal = 0;
            document.querySelectorAll('#sparepartItemsList > div').forEach(row => {
                const select = row.querySelector('select');
                const quantity = row.querySelector('input');
                const selectedOpt = select.options[select.selectedIndex];
                sparepartSubtotal += (Number(selectedOpt?.dataset.harga) || 0) * (parseInt(quantity.value, 10) || 0);
            });

            // Estimasi total: Jika ada sparepart baru ditambah jasa
            const previousSparepartTotal = Math.max(0, baseTotalBiaya - currentJasa);
            const newTotal = inputJasa + previousSparepartTotal + sparepartSubtotal;
            document.getElementById('total_biaya').value = newTotal;
        }

        async function initData() {
            const select = document.getElementById('service_id');
            const selectedServiceId = select.value;
            await Promise.all([loadPendingServices(), loadSpareparts(), loadServiceRates(), loadWarrantyPresets()]);
            if (servicesCache.some(service => String(service.id) === selectedServiceId)) {
                select.value = selectedServiceId;
                document.getElementById('serviceDetailWrap').classList.remove('hidden');
                await onSelectService();
            } else {
                select.value = '';
                closeServiceDetail();
                document.getElementById('statusTimelineWrap').classList.add('hidden');
            }
        }
        initData();
        let serviceFilterTimer;
        document.getElementById('serviceSearchFilter').addEventListener('input', () => {
            clearTimeout(serviceFilterTimer);
            serviceFilterTimer = setTimeout(() => loadPendingServices(1), 250);
        });
        document.getElementById('addSparepartItem').addEventListener('click', addSparepartRow);
        document.querySelectorAll('.work-filter-tab').forEach(button => {
            button.addEventListener('click', () => {
                setWorkFilter(button.dataset.workFilter);
                loadPendingServices(1);
            });
        });
        document.getElementById('openEstimateChange').addEventListener('click', () => {
            document.getElementById('estimateChangeForm').classList.toggle('hidden');
            document.getElementById('estimateChangeReason').focus();
        });
        document.getElementById('estimateChangeForm').addEventListener('submit', async event => {
            event.preventDefault();
            const button = event.currentTarget.querySelector('button[type="submit"]');
            const feedback = document.getElementById('estimateChangeFeedback');
            button.disabled = true;
            feedback.className = 'text-sm text-slate-600';
            feedback.textContent = 'Mengirim usulan…';
            try {
                const response = await fetch('/api/admin/service/estimate-changes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_id: document.getElementById('service_id').value,
                        additional_days: Number(document.getElementById('estimateChangeDays').value),
                        reason: document.getElementById('estimateChangeReason').value,
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Usulan perubahan estimasi gagal dikirim.');
                feedback.className = 'text-sm font-semibold text-emerald-700';
                feedback.textContent = result.message;
                event.currentTarget.reset();
                await onSelectService();
            } catch (error) {
                feedback.className = 'text-sm font-semibold text-rose-700';
                feedback.textContent = error.message || 'Usulan perubahan estimasi gagal dikirim.';
            } finally {
                button.disabled = false;
            }
        });

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').catch(error => {
                console.error('PWA panel teknisi gagal diinisialisasi', error);
            });
        }

        window.addEventListener('load', () => {
            if (!window.ServiceRealtime) return;
            window.ServiceRealtime.startStaff('teknisi', async () => {
                await initData();
            });
        });
        window.addEventListener('panel-notification', () => initData());

        async function submitUpdate() {
            const serviceId = document.getElementById('service_id').value;
            const statusBaru = document.getElementById('status_baru').value;
            const catatanTeknis = document.getElementById('catatan_teknis').value;
            const completionPhotos = document.getElementById('completion_photos').files;
            const rateId = document.getElementById('service_rate_id').value;

            if (!serviceId || !catatanTeknis) {
                return alert('Pilih unit servis dan isi catatan diagnosa / pengerjaan!');
            }

            const sparepartItems = [];
            const seenSpareparts = new Set();
            for (const row of document.querySelectorAll('#sparepartItemsList > div')) {
                const select = row.querySelector('select');
                const quantity = row.querySelector('input');
                if (!select.value) continue;
                const qty = Number.parseInt(quantity.value, 10);
                const selectedOpt = select.options[select.selectedIndex];
                const id = Number.parseInt(select.value, 10);
                if (!Number.isInteger(qty) || qty < 1) return alert('Masukkan jumlah minimal 1 untuk setiap sparepart.');
                if (seenSpareparts.has(id)) return alert('Pilih setiap jenis sparepart satu kali saja; ubah jumlah pada barisnya.');
                if (qty > Number(selectedOpt.dataset.stok)) return alert(`Jumlah ${selectedOpt.textContent} melebihi stok yang tersedia.`);
                seenSpareparts.add(id);
                sparepartItems.push({ sparepart_id: id, jumlah: qty });
            }

            const payload = {
                service_id: serviceId,
                status_baru: statusBaru,
                service_rate_id: rateId ? Number(rateId) : null,
                warranty_preset_id: document.getElementById('warranty_preset_id').value || null,
                catatan_teknis: catatanTeknis,
                sparepart_items: sparepartItems
            };

            try {
                const res = await fetch('/api/admin/service/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.status === 'success') {
                    let photoError = '';
                    if (completionPhotos.length) {
                        try {
                            const photoData = new FormData();
                            photoData.append('service_id', serviceId);
                            photoData.append('kind', 'completion');
                            Array.from(completionPhotos).forEach(file => photoData.append('photos[]', file));
                            const photoResponse = await fetch('/api/admin/service/photos', { method: 'POST', body: photoData });
                            const photoResult = await photoResponse.json();
                            if (!photoResponse.ok) photoError = photoResult.message || 'Foto selesai gagal diunggah.';
                        } catch (error) {
                            photoError = error.message || 'Foto selesai gagal diunggah.';
                        }
                    }
                    alert(photoError
                        ? `Progres tersimpan, tetapi ${photoError}`
                        : data.status_baru === 'menunggu_persetujuan'
                            ? 'Perubahan biaya menunggu persetujuan pelanggan.'
                            : '✅ Progres pengerjaan, estimasi, dan onderdil berhasil diperbarui!');
                    document.getElementById('formUpdate').reset();
                    document.getElementById('sparepart_info').innerText = '';
                    document.getElementById('unitInfoBanner').classList.add('hidden');
                    initData();
                } else {
                    alert(`Gagal: ${data.message}`);
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat menghubungi server.');
            }
        }
    </script>
    <script src="/panel-notifications.js"></script>
    <script src="/account-menu.js" data-role="teknisi"></script>
</body>

</html>
