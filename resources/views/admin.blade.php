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
    <title>Panel Admin - TECKFO Service</title>
    @include('partials.realtime-scripts')
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>

<body data-theme="dashboard" class="bg-slate-50 min-h-screen flex flex-col font-sans text-slate-900">
    <!-- Topbar Navigasi -->
    <div class="app-topbar">
        <a href="admin.html" class="app-nav-link">🏢 Panel Admin</a>
    </div>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto space-y-8">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#0B1E3F] text-amber-400 mb-2 border border-amber-400/40">
                        ⚡ Service Desk & Front Office
                    </span>
                    <h1 class="text-2xl font-extrabold text-[#0B1E3F] tracking-tight">Panel Administrasi Servis</h1>
                    <p class="text-xs text-slate-500 mt-1">Pendaftaran servis unit baru, kasir pelunasan, dan kelola data pelanggan</p>
                </div>
            </div>

            <!-- 1. Form Pendaftaran Servis Baru -->
            <div class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400">
                <div class="flex items-center gap-3.5 mb-6 pb-4 border-b border-slate-100">
                    <span class="w-11 h-11 bg-[#0B1E3F] text-amber-400 border border-amber-400/50 rounded-xl flex items-center justify-center text-xl shadow-sm font-bold">📝</span>
                    <div>
                        <h2 class="text-xl font-bold text-[#0B1E3F]">Pendaftaran Servis Baru</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Input unit masuk baru dari pelanggan dan terbitkan kode akses pelacakan</p>
                    </div>
                </div>

                <form id="formPendaftaran" class="space-y-5">
                    <div class="relative rounded-xl border border-amber-300 bg-amber-50/60 p-4">
                        <input type="hidden" id="customer_id">
                        <label for="cariPelangganLama" class="block text-xs font-bold uppercase tracking-wider text-[#0B1E3F] mb-1.5">
                            Cari Pelanggan Lama (Nama / No. WhatsApp)
                        </label>
                        <input type="text" id="cariPelangganLama" autocomplete="off" placeholder="Ketik nama atau nomor WhatsApp..."
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition bg-white">
                        <div id="hasilPencarianPelanggan"
                            class="absolute left-4 right-4 z-40 mt-1 hidden max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nama Lengkap Pelanggan <span class="text-rose-500">*</span></label>
                        <input type="text" id="nama" required placeholder="Contoh: Budi Prasetyo"
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nomor WhatsApp <span class="text-rose-500">*</span></label>
                            <input type="text" id="no_wa" required placeholder="08xxxxxxxxxx"
                                class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Jenis Unit <span class="text-rose-500">*</span></label>
                            <select id="jenis_unit"
                                class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition bg-white">
                                <option value="Laptop">💻 Laptop</option>
                                <option value="PC">🖥️ PC / Komputer Desktop</option>
                                <option value="Printer">🖨️ Printer</option>
                                <option value="Lainnya">🔌 Perangkat Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Merek / Tipe Unit <span class="text-rose-500">*</span></label>
                            <input type="text" id="merek_tipe" required placeholder="Contoh: Asus Vivobook A412DA"
                                class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nomor Seri <span class="text-slate-400 font-normal">(Opsional)</span></label>
                            <input type="text" id="no_seri" placeholder="Contoh: SN-88392193"
                                class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                        </div>
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="block text-xs font-bold uppercase tracking-wider text-slate-700">Checklist Kelengkapan Unit</legend>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <label><input type="checkbox" name="accessories" value="Charger"> Charger</label>
                            <label><input type="checkbox" name="accessories" value="Tas"> Tas</label>
                            <label><input type="checkbox" name="accessories" value="Baterai"> Baterai</label>
                            <label><input type="checkbox" name="accessories" value="Kabel daya"> Kabel daya</label>
                            <label><input type="checkbox" name="accessories" value="Lainnya"> Lainnya</label>
                        </div>
                    </fieldset>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Kelengkapan lainnya / catatan</label>
                        <input type="text" id="kelengkapan" maxlength="1000" placeholder="Detail aksesori lain yang ikut diterima"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Catatan Kondisi Fisik Saat Masuk</label>
                        <textarea id="physical_condition" rows="2" maxlength="5000" placeholder="Contoh: goresan di sudut kiri, layar utuh"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Foto Kondisi Saat Masuk (maks. 5 foto, 5 MB/foto)</label>
                            <input type="file" id="intake_photos" accept="image/jpeg,image/png" multiple class="w-full rounded-xl border border-slate-300 p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Estimasi Selesai</label>
                            <input type="date" id="estimated_completion_at" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Prioritas</label>
                            <select id="priority" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                                <option value="low">Rendah</option><option value="normal" selected>Normal</option>
                                <option value="high">Tinggi</option><option value="urgent">Mendesak</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Teknisi (beban aktif)</label>
                            <select id="teknisi_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                                <option value="">Belum ditugaskan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Garansi (hari setelah diambil)</label>
                            <input type="number" id="warranty_days" min="0" max="3650" value="0" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Keluhan Kerusakan / Kendala <span class="text-rose-500">*</span></label>
                        <textarea id="keluhan" required rows="3"
                            placeholder="Deskripsikan kendala atau kerusakan yang dialami pelanggan secara rinci..."
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition"></textarea>
                    </div>

                    <button type="button" onclick="simpanServis()"
                        class="w-full bg-[#0B1E3F] hover:bg-[#132F63] text-white font-bold py-3.5 px-6 rounded-xl transition border border-amber-400/80 shadow-md flex items-center justify-center gap-2 text-sm tracking-wide">
                        <span>📥</span> Simpan Servis & Tampilkan Kode
                    </button>
                </form>
            </div>

            <!-- 2. Tabel Daftar Unit Servis Aktif & Kasir Pelunasan -->
            <div class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400 space-y-5">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3.5">
                        <span class="w-11 h-11 bg-[#0B1E3F] text-amber-400 border border-amber-400/50 rounded-xl flex items-center justify-center text-xl shadow-sm font-bold">💳</span>
                        <div>
                            <h2 class="text-xl font-bold text-[#0B1E3F]">Daftar Unit Servis Aktif & Kasir Pelunasan</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Kelola kasir pelunasan, pembayaran (Cash / QRIS / Transfer), dan penyerahan unit ke pelanggan</p>
                        </div>
                    </div>
                    <button onclick="loadActiveServices()"
                        class="inline-flex items-center gap-2 bg-slate-50 hover:bg-slate-100 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-300 transition shadow-sm">
                        🔄 Refresh Data
                    </button>
                </div>

                <!-- Tabel Data -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[#0B1E3F] text-white border-b-2 border-amber-400 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="p-3.5">Kode Akses</th>
                                <th class="p-3.5">Kode Akses Pelanggan</th>
                                <th class="p-3.5">Nama Pelanggan</th>
                                <th class="p-3.5">Merek / Tipe</th>
                                <th class="p-3.5">Total Biaya</th>
                                <th class="p-3.5">Status Bayar</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="activeServicesTable" class="divide-y divide-slate-100 bg-white">
                            <!-- Data dimuat via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6">
                    <input id="serviceSearch" type="search" placeholder="Cari nota, pelanggan, unit..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
                    <select id="serviceStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        <option value="diperiksa">Diperiksa</option><option value="menunggu_persetujuan">Menunggu persetujuan</option>
                        <option value="dikerjakan">Dikerjakan</option><option value="menunggu_sparepart">Menunggu sparepart</option>
                        <option value="selesai">Selesai</option><option value="diambil">Diambil</option><option value="batal">Batal</option>
                    </select>
                    <select id="serviceTechnicianFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua teknisi</option>
                    </select>
                    <input id="serviceDateFrom" type="date" aria-label="Tanggal servis dari" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select id="serviceSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" aria-label="Urutkan servis">
                        <option value="created_at:desc">Terbaru</option><option value="created_at:asc">Terlama</option>
                        <option value="status:asc">Status A-Z</option><option value="total_biaya:desc">Biaya tertinggi</option>
                    </select>
                    <input id="serviceDateTo" type="date" aria-label="Tanggal servis sampai" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div id="servicePagination" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600" aria-live="polite"></div>
            </div>

            <!-- 3. Kelola data pelanggan -->
            <section class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400 space-y-5" aria-labelledby="customersTitle">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3.5">
                        <span class="w-11 h-11 bg-[#0B1E3F] text-amber-400 border border-amber-400/50 rounded-xl flex items-center justify-center text-xl shadow-sm font-bold">👥</span>
                        <div>
                            <h2 id="customersTitle" class="text-xl font-bold text-[#0B1E3F]">Data Pelanggan & Kontak</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Daftar pelanggan terdaftar dan akumulasi riwayat servis unit.</p>
                        </div>
                    </div>
                    <button type="button" onclick="loadCustomers()"
                        class="inline-flex items-center gap-2 bg-slate-50 hover:bg-slate-100 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-300 transition shadow-sm">
                        🔄 Muat Ulang
                    </button>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50/80 p-3.5 text-xs leading-relaxed text-blue-900 flex items-start gap-2.5">
                    <span class="text-base flex-shrink-0">🔒</span>
                    <span>Pelanggan yang dihapus dipindahkan ke arsip, bukan dihapus permanen. Riwayat servis, detail unit, dan kode akses tetap tersimpan aman di database.</span>
                </div>
                <div class="relative">
                    <label for="cariPelanggan" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Cari Pelanggan
                    </label>
                    <input type="search" id="cariPelanggan" autocomplete="off"
                        placeholder="Nama, nomor WhatsApp, kode servis, atau kode akses..."
                        class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <select id="customerStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua status servis</option>
                        <option value="diperiksa">Diperiksa</option><option value="menunggu_persetujuan">Menunggu persetujuan</option>
                        <option value="dikerjakan">Dikerjakan</option><option value="menunggu_sparepart">Menunggu sparepart</option>
                        <option value="selesai">Selesai</option><option value="diambil">Diambil</option><option value="batal">Batal</option>
                    </select>
                    <select id="customerTechnicianFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua teknisi</option>
                    </select>
                    <input id="customerDateFrom" type="date" aria-label="Tanggal servis pelanggan dari" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input id="customerDateTo" type="date" aria-label="Tanggal servis pelanggan sampai" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select id="customerSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="nama:asc">Nama A–Z</option><option value="nama:desc">Nama Z–A</option><option value="created_at:desc">Terbaru</option><option value="services_count:desc">Servis terbanyak</option>
                    </select>
                </div>
                <p id="customersMessage" class="text-sm text-slate-600 font-semibold" role="status" aria-live="polite"></p>
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[#0B1E3F] text-white border-b-2 border-amber-400 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="p-3.5">Nama Pelanggan</th>
                                <th class="p-3.5">Nomor WhatsApp</th>
                                <th class="p-3.5 text-center">Riwayat Servis</th>
                                <th class="p-3.5">Terdaftar Sejak</th>
                                <th class="p-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="customersTable" class="divide-y divide-slate-100 bg-white">
                            <tr><td colspan="5" class="p-6 text-center text-slate-400">Memuat data pelanggan…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="customerPagination" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600" aria-live="polite"></div>
            </section>
        </div>
    </main>

    <div id="serviceSuccessModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-[#0B1E3F]/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="serviceSuccessTitle">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 bg-[#0B1E3F] px-6 py-5 text-white">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-300">Pendaftaran berhasil</p>
                    <h2 id="serviceSuccessTitle" class="mt-1 text-xl font-extrabold">Kode Akses Pelanggan</h2>
                    <p id="successServiceNumber" class="mt-1 text-xs text-slate-300"></p>
                </div>
                <button type="button" onclick="closeServiceSuccessModal()" aria-label="Tutup notifikasi sukses"
                    class="rounded-lg p-1 text-2xl leading-none text-slate-300 hover:bg-white/10 hover:text-white">&times;</button>
            </div>
            <div class="space-y-4 p-6">
                <p class="text-sm text-slate-600">Berikan kode berikut kepada pelanggan untuk melacak status servisnya.</p>
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-center">
                    <p id="successCustomerName" class="text-sm font-bold text-[#0B1E3F]"></p>
                    <p id="successDevice" class="mt-1 text-xs text-slate-600"></p>
                    <code id="successAccessCode" class="mt-3 inline-block rounded-lg border border-amber-400 bg-white px-5 py-2 font-mono text-2xl font-extrabold tracking-[0.3em] text-[#0B1E3F]"></code>
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button id="successCopyButton" type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-amber-400/80 bg-[#0B1E3F] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#132F63]">
                        Salin Kode
                    </button>
                    <a id="successWhatsappLink" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-xl border border-amber-400/80 bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">
                        💬 Kirim ke WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Servis Pelanggan -->
    <div id="modalRiwayatPelanggan" class="fixed inset-0 z-[58] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="riwayatPelangganTitle">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 bg-[#0B1E3F] px-6 py-4 text-white">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-300">Riwayat servis</p>
                    <h2 id="riwayatPelangganTitle" class="mt-1 text-lg font-extrabold"></h2>
                    <p id="riwayatPelangganKontak" class="mt-1 text-xs text-slate-300"></p>
                </div>
                <button type="button" onclick="closeRiwayatPelanggan()" aria-label="Tutup"
                    class="rounded-lg p-1 text-2xl leading-none text-slate-300 hover:bg-white/10 hover:text-white">&times;</button>
            </div>
            <div id="riwayatPelangganIsi" class="max-h-[68vh] space-y-3 overflow-y-auto p-5"></div>
        </div>
    </div>

    <!-- Modal Edit Pelanggan -->
    <div id="modalEditPelanggan" class="fixed inset-0 z-[55] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="editPelangganTitle">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b-2 border-amber-400 bg-[#0B1E3F] px-6 py-4 text-white">
                <div>
                    <h2 id="editPelangganTitle" class="text-lg font-bold">Perbarui Data Pelanggan</h2>
                    <p class="mt-0.5 text-xs text-slate-300">Riwayat dan kode akses servis sebelumnya tidak akan berubah.</p>
                </div>
                <button type="button" onclick="closeEditPelanggan()" aria-label="Tutup"
                    class="rounded-lg p-1 text-2xl leading-none text-slate-300 hover:bg-white/10 hover:text-white">&times;</button>
            </div>
            <form id="formEditPelanggan" onsubmit="simpanEditPelanggan(event)" class="space-y-4 p-6">
                <input type="hidden" id="editPelangganId">
                <div>
                    <label for="editPelangganNama" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nama Lengkap Pelanggan</label>
                    <input id="editPelangganNama" type="text" maxlength="100" required
                        class="w-full rounded-xl border border-slate-300 py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                </div>
                <div>
                    <label for="editPelangganWa" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nomor WhatsApp</label>
                    <input id="editPelangganWa" type="tel" maxlength="20" required
                        class="w-full rounded-xl border border-slate-300 py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                </div>
                <div>
                    <label for="editPelangganAlamat" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Alamat Domisili <span class="font-normal text-slate-400">(Opsional)</span></label>
                    <textarea id="editPelangganAlamat" rows="3" maxlength="10000"
                        class="w-full resize-y rounded-xl border border-slate-300 py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500"></textarea>
                </div>
                <p id="editPelangganError" class="min-h-5 text-xs font-semibold text-rose-600" role="alert" aria-live="polite"></p>
                <div class="flex justify-end gap-2.5 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeEditPelanggan()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Batal</button>
                    <button id="savePelangganButton" type="submit" class="rounded-xl bg-[#0B1E3F] hover:bg-[#132F63] border border-amber-400/80 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition disabled:cursor-wait disabled:opacity-60">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kasir Pelunasan & Ambil Unit -->
    <div id="modalPelunasan"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
            <!-- Modal Header -->
            <div class="bg-[#0B1E3F] text-white px-6 py-4 flex justify-between items-center border-b-2 border-amber-400">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 bg-amber-500 text-slate-950 rounded-lg text-sm font-bold">💵</span>
                    <h3 class="text-base font-bold">Kasir: Pelunasan & Ambil Unit</h3>
                </div>
                <button type="button" onclick="closeModalPelunasan()"
                    class="text-slate-300 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Modal Form -->
            <form id="formPelunasan" onsubmit="submitPelunasan(event)" class="p-6 space-y-4">
                <input type="hidden" id="modal_service_id">

                <!-- Ringkasan Unit -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Kode Akses:</span>
                        <span id="modal_no_nota" class="font-mono font-bold text-[#0B1E3F] text-sm"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Pelanggan:</span>
                        <span id="modal_nama_pelanggan" class="font-bold text-slate-800"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Unit Perangkat:</span>
                        <span id="modal_unit" class="text-slate-700 font-medium"></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-200 pt-2 text-sm">
                        <span class="font-bold text-slate-700">Total Biaya:</span>
                        <span id="modal_total_biaya" class="font-black text-emerald-600 text-base"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Status Bayar Saat Ini:</span>
                        <span id="modal_status_bayar_badge"></span>
                    </div>
                </div>

                <!-- Opsi Metode Pembayaran -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Metode Pembayaran (Pelunasan)</label>
                    <select id="modal_metode_pembayaran" required
                        class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 font-medium">
                        <option value="Cash">💵 Cash (Tunai)</option>
                        <option value="QRIS">📱 QRIS</option>
                        <option value="Transfer">💳 Transfer Bank</option>
                    </select>
                </div>

                <!-- Catatan Kasir -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Catatan Kasir / Keterangan (Opsional)</label>
                    <input type="text" id="modal_catatan"
                        placeholder="Contoh: Unit diserahkan lengkap, kondisi fisik normal"
                        class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                </div>

                <div class="p-3 bg-amber-50 rounded-xl border border-amber-300 text-xs text-amber-950 space-y-1">
                    <p class="font-bold flex items-center gap-1.5"><span>⚠️</span> Informasi Pelunasan:</p>
                    <p>Status pembayaran akan diubah menjadi <strong class="text-emerald-700">Lunas</strong> dan status unit servis akan diubah menjadi <strong class="text-[#0B1E3F]">Diambil</strong>.</p>
                </div>

                <!-- Tombol Aksi Modal -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModalPelunasan()"
                        class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition border border-slate-300">
                        Batal
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-md border border-amber-600">
                        Konfirmasi & Ambil Unit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sessionCustomerAccessCodes = new Map();
        const activeCustomerAccessCodes = new Set();

        function formatNomorWA(noHp) {
            const nomor = String(noHp || '').replace(/\D/g, '');
            return nomor.startsWith('0') ? `62${nomor.slice(1)}` : nomor;
        }

        function buatLinkWA(noHp, namaPelanggan, perangkat, kodeAkses) {
            const nomor = formatNomorWA(noHp);
            if (!nomor) return '';

            const linkStatus = `${window.location.origin}/index.html`;
            const pesan = `Halo *${namaPelanggan}*,\n\nPerangkat Anda (*${perangkat}*) telah terdaftar di sistem servis kami.\n\n*Kode Akses Pelanggan:* *${kodeAkses}*\n*Link Cek Status:* ${linkStatus}\n\nMelalui link di atas, Anda dapat:\n1. Memantau progres pengerjaan servis secara real-time.\n2. Melihat hasil pengecekan teknisi & rincian estimasi biaya.\n3. Melakukan *konfirmasi setuju (lanjut servis) atau batal* setelah pengecekan selesai.\n\nSimpan kode akses Anda untuk pengecekan berkala. Terima kasih!`;
            return `https://wa.me/${nomor}?text=${encodeURIComponent(pesan)}`;
        }

        function tandaiLinkWATidakTersedia(link, alasan) {
            link.classList.add('cursor-not-allowed', 'opacity-50');
            link.setAttribute('aria-disabled', 'true');
            link.title = alasan;
            link.onclick = event => {
                event.preventDefault();
                alert(alasan);
            };
        }

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        async function loadAccessCodes() {
            const response = await fetch('/api/admin/customers/access-codes');
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Status kode akses gagal dimuat.');
            }

            activeCustomerAccessCodes.clear();
            result.data.forEach(service => {
                if (Number(service.has_active_code)) {
                    activeCustomerAccessCodes.add(String(service.service_id));
                }
            });
        }

        // Ambil dan Tampilkan Daftar Unit Servis Aktif
        let servicePage = 1;
        let customerPage = 1;

        function renderTablePagination(elementId, meta, onPage) {
            const container = document.getElementById(elementId);
            container.replaceChildren();
            if (!meta || !meta.total) {
                container.textContent = 'Tidak ada data untuk filter ini.';
                return;
            }
            const first = (meta.current_page - 1) * meta.per_page + 1;
            const last = Math.min(meta.current_page * meta.per_page, meta.total);
            const summary = document.createElement('span');
            summary.textContent = `Menampilkan ${first}–${last} dari ${meta.total}`;
            const actions = document.createElement('div');
            actions.className = 'flex gap-2';
            [['Sebelumnya', meta.current_page - 1], ['Berikutnya', meta.current_page + 1]].forEach(([label, page]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.disabled = label === 'Sebelumnya' ? meta.current_page <= 1 : meta.current_page >= meta.last_page;
                button.className = 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-40';
                button.addEventListener('click', () => onPage(page));
                actions.append(button);
            });
            container.append(summary, actions);
        }

        async function loadActiveServices(page = 1) {
            servicePage = page;
            const tbody = document.getElementById('activeServicesTable');
            tbody.innerHTML = '<tr><td colspan="7" class="p-6 text-center text-slate-500">Memuat daftar servis…</td></tr>';
            try {
                await loadAccessCodes();
                const params = new URLSearchParams({
                    page: String(servicePage),
                    per_page: '20',
                    sort: document.getElementById('serviceSort').value.split(':')[0],
                    direction: document.getElementById('serviceSort').value.split(':')[1],
                });
                const search = document.getElementById('serviceSearch').value.trim();
                const status = document.getElementById('serviceStatusFilter').value;
                const technicianId = document.getElementById('serviceTechnicianFilter').value;
                const dateFrom = document.getElementById('serviceDateFrom').value;
                const dateTo = document.getElementById('serviceDateTo').value;
                if (search) params.set('search', search);
                if (status) params.set('status', status);
                if (technicianId) params.set('teknisi_id', technicianId);
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);
                const res = await fetch(`/api/admin/services-active?${params}`);
                const result = await res.json();
                tbody.innerHTML = '';

                if (result.status === 'success' && result.data.length > 0) {
                    result.data.forEach(srv => {
                        const totalBiayaRp = Number(srv.total_biaya || 0).toLocaleString('id-ID');
                        const noWaDisplay = srv.no_wa || '-';

                        let badgeBayar = '';
                        if (srv.status_pembayaran === 'lunas') {
                            badgeBayar = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✅ Lunas</span>';
                        } else if (srv.status_pembayaran === 'dp') {
                            badgeBayar = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300">⏳ DP</span>';
                        } else {
                            badgeBayar = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">⚠️ Belum Bayar</span>';
                        }

                        const tr = document.createElement('tr');
                        tr.id = `service-row-${srv.id}`;
                        tr.className = 'hover:bg-amber-50/40 transition-colors';
                        tr.innerHTML = `
                            <td class="p-3.5 font-mono font-bold text-[#0B1E3F] whitespace-nowrap">${srv.no_nota}</td>
                            <td class="p-3.5 whitespace-nowrap"></td>
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">${srv.nama_pelanggan}</div>
                                <div class="text-xs text-slate-500">${noWaDisplay}</div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-semibold text-slate-800">${srv.merek_tipe}</div>
                                <div class="text-xs text-slate-500 capitalize">${srv.jenis_unit || ''} • Status: <span class="font-bold text-[#0B1E3F]">${(srv.status || '').replace('_', ' ')}</span></div>
                                <div class="text-xs font-semibold text-amber-800">Prioritas: ${(srv.priority || 'normal').toUpperCase()} • Teknisi: ${srv.nama_teknisi || 'Belum ditugaskan'}</div>
                            </td>
                            <td class="p-3.5 whitespace-nowrap font-black text-slate-900">Rp ${totalBiayaRp}</td>
                            <td class="p-3.5 whitespace-nowrap">${badgeBayar}</td>
                            <td class="p-3.5 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="flex max-w-48 flex-col gap-1">
                                        <select class="assign-technician rounded-lg border border-slate-300 px-2 py-1 text-[10px]" aria-label="Pilih teknisi"></select>
                                        <select class="assign-priority rounded-lg border border-slate-300 px-2 py-1 text-[10px]" aria-label="Pilih prioritas">
                                            <option value="low">Rendah</option><option value="normal">Normal</option>
                                            <option value="high">Tinggi</option><option value="urgent">Mendesak</option>
                                        </select>
                                        <button type="button" class="assign-service rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold">Simpan penugasan</button>
                                    </div>
                                    <button onclick="openModalPelunasan(${srv.id}, '${srv.no_nota}', '${encodeURIComponent(srv.nama_pelanggan)}', '${encodeURIComponent(srv.merek_tipe)}', ${srv.total_biaya || 0}, '${srv.status_pembayaran}')"
                                        class="inline-flex items-center gap-1.5 bg-[#0B1E3F] hover:bg-[#132F63] text-white px-3.5 py-2 rounded-xl text-xs font-bold border border-amber-400/80 shadow-sm transition">
                                        💵 Pelunasan
                                    </button>
                                    <button type="button" class="service-history-button inline-flex items-center gap-1 rounded-xl border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-800 transition hover:bg-blue-100">
                                        Riwayat
                                    </button>
                                    <a href="/api/admin/receipts/${srv.id}/intake" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700">Tanda terima</a>
                                    <a href="/api/admin/receipts/${srv.id}/payment" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700">Nota</a>
                                    <button type="button" class="delete-service-button inline-flex items-center gap-1 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </td>
                        `;
                        tr.querySelector('.service-history-button').addEventListener('click', () => showServiceHistory(srv.no_nota));
                        tr.querySelector('.delete-service-button').addEventListener('click', () => hapusServis(srv.id, srv.no_nota));
                        const technicianSelect = tr.querySelector('.assign-technician');
                        technicianSelect.replaceChildren(new Option('Belum ditugaskan', ''));
                        technicianWorkloads.forEach(technician => technicianSelect.add(new Option(
                            `${technician.nama} · ${technician.beban_kerja} aktif`,
                            technician.id,
                        )));
                        technicianSelect.value = srv.teknisi_id || '';
                        tr.querySelector('.assign-priority').value = srv.priority || 'normal';
                        tr.querySelector('.assign-service').addEventListener('click', () => saveServiceAssignment(
                            srv.id,
                            technicianSelect.value,
                            tr.querySelector('.assign-priority').value,
                        ));
                        const accessCodeCell = tr.cells[1];
                        const accessCode = sessionCustomerAccessCodes.get(String(srv.id));
                        const waNumber = formatNomorWA(srv.no_wa);
                        const codeWrap = document.createElement('div');
                        codeWrap.className = 'flex flex-col items-start gap-2';
                        const sendWhatsappLink = document.createElement('a');
                        sendWhatsappLink.className = 'send-whatsapp-link inline-flex items-center gap-1 rounded-xl border border-amber-400/80 bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-800';
                        sendWhatsappLink.target = '_blank';
                        sendWhatsappLink.rel = 'noopener noreferrer';
                        sendWhatsappLink.textContent = '💬 Kirim WA';
                        if (accessCode) {
                            const code = document.createElement('code');
                            code.className = 'inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 font-mono text-base font-extrabold tracking-[0.2em] text-[#0B1E3F]';
                            code.textContent = accessCode;
                            const copyButton = document.createElement('button');
                            copyButton.type = 'button';
                            copyButton.className = 'inline-flex items-center rounded-lg border border-amber-400/80 bg-[#0B1E3F] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#132F63]';
                            copyButton.textContent = 'Salin';
                            copyButton.addEventListener('click', () => copyCustomerAccessCode(accessCode, copyButton));
                            codeWrap.append(code, copyButton);
                            if (waNumber) {
                                sendWhatsappLink.href = buatLinkWA(
                                    srv.no_wa,
                                    srv.nama_pelanggan,
                                    `${srv.jenis_unit || ''} ${srv.merek_tipe || ''}`.trim(),
                                    accessCode
                                );
                            } else {
                                tandaiLinkWATidakTersedia(sendWhatsappLink, 'Nomor WhatsApp pelanggan tidak tersedia');
                            }

                        } else {
                            accessCodeCell.className += ' text-xs text-slate-500';
                            const unavailableCode = document.createElement('span');
                            const hasActiveCode = activeCustomerAccessCodes.has(String(srv.id));
                            unavailableCode.textContent = hasActiveCode
                                ? 'Kode aktif tersimpan aman; buat ulang untuk menampilkannya.'
                                : 'Belum ada kode aktif.';
                            const createCodeButton = document.createElement('button');
                            createCodeButton.type = 'button';
                            createCodeButton.className = 'inline-flex items-center rounded-lg border border-amber-400/80 bg-[#0B1E3F] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#132F63]';
                            createCodeButton.textContent = hasActiveCode ? 'Buat Ulang' : 'Buat Kode';
                            createCodeButton.addEventListener('click', () => rotateCustomerAccessCode(srv.id, createCodeButton));
                            codeWrap.append(unavailableCode, createCodeButton);
                            tandaiLinkWATidakTersedia(
                                sendWhatsappLink,
                                !waNumber
                                    ? 'Nomor WhatsApp pelanggan tidak tersedia'
                                    : 'Kode akses belum ditampilkan; buat kode pada kolom Kode Akses terlebih dahulu'
                            );
                        }
                        codeWrap.appendChild(sendWhatsappLink);
                        accessCodeCell.appendChild(codeWrap);
                        tbody.appendChild(tr);
                    });
                    renderTablePagination('servicePagination', result.meta, loadActiveServices);
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 text-sm">Tidak ada unit servis aktif saat ini.</td></tr>';
                    renderTablePagination('servicePagination', result.meta, loadActiveServices);
                }
            } catch (err) {
                console.error('Gagal memuat servis aktif', err);
                tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-rose-600 text-sm">Daftar servis gagal dimuat. Periksa koneksi, lalu coba muat ulang.</td></tr>';
                document.getElementById('servicePagination').textContent = 'Terjadi kesalahan saat memuat data.';
            }
        }

        async function showServiceHistory(noNota) {
            let dialog = document.getElementById('serviceHistoryDialog');
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.id = 'serviceHistoryDialog';
                dialog.className = 'w-[min(640px,calc(100%-2rem))] max-h-[85vh] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50';
                dialog.innerHTML = `
                    <div class="flex items-center justify-between border-b border-slate-200 bg-[#0B1E3F] p-4 text-white">
                        <h2 class="font-bold">Riwayat Status Servis</h2>
                        <button type="button" class="close-history rounded-lg px-3 py-1 text-sm font-bold text-amber-300 hover:bg-white/10" aria-label="Tutup">Tutup</button>
                    </div>
                    <ol class="history-list max-h-[65vh] space-y-3 overflow-auto p-5"></ol>`;
                dialog.querySelector('.close-history').addEventListener('click', () => dialog.close());
                dialog.addEventListener('click', event => {
                    if (event.target === dialog) dialog.close();
                });
                document.body.appendChild(dialog);
            }

            const list = dialog.querySelector('.history-list');
            list.replaceChildren();
            const loading = document.createElement('li');
            loading.className = 'text-sm text-slate-500';
            loading.textContent = 'Memuat riwayat...';
            list.appendChild(loading);
            if (!dialog.open) dialog.showModal();

            try {
                const response = await fetch(`/api/admin/nota/${encodeURIComponent(noNota)}`);
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Riwayat servis gagal dimuat.');
                }

                list.replaceChildren();
                const logs = result.data.status_timeline || [];
                if (!logs.length) {
                    const empty = document.createElement('li');
                    empty.className = 'text-sm italic text-slate-500';
                    empty.textContent = 'Belum ada riwayat status.';
                    list.appendChild(empty);
                }
                logs.forEach(log => {
                    const item = document.createElement('li');
                    item.className = 'border-l-2 border-amber-400 pl-4';
                    const status = document.createElement('strong');
                    status.className = 'block text-sm uppercase text-[#0B1E3F]';
                    status.textContent = (log.status_baru || 'Update').replaceAll('_', ' ');
                    const metadata = document.createElement('time');
                    metadata.className = 'block text-xs text-slate-500';
                    metadata.dateTime = log.created_at || '';
                    metadata.textContent = `${log.user || 'Pelanggan / sistem'}${log.user_role ? ` · ${log.user_role}` : ''} · ${log.created_at ? new Date(log.created_at).toLocaleString('id-ID') : '-'}`;
                    const note = document.createElement('p');
                    note.className = 'mt-1 whitespace-pre-wrap text-sm text-slate-700';
                    note.textContent = log.catatan || 'Tidak ada catatan.';
                    item.append(status, metadata, note);
                    list.appendChild(item);
                });
            } catch (error) {
                list.replaceChildren();
                const failure = document.createElement('li');
                failure.className = 'text-sm text-rose-700';
                failure.textContent = error.message || 'Riwayat servis gagal dimuat.';
                list.appendChild(failure);
            }
        }

        async function rotateCustomerAccessCode(serviceId, button) {
            const hasActiveCode = activeCustomerAccessCodes.has(String(serviceId));
            const confirmation = hasActiveCode
                ? 'Buat kode akses baru? Kode sebelumnya langsung tidak berlaku dan sesi portal pelanggan akan dicabut.'
                : 'Buat kode akses untuk servis ini?';
            if (!confirm(confirmation)) return;

            button.disabled = true;
            try {
                const response = await fetch(`/api/admin/customers/access-codes/${encodeURIComponent(serviceId)}`, { method: 'POST' });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Kode akses gagal dibuat.');
                }

                sessionCustomerAccessCodes.set(String(result.data.service_id), result.data.customer_token);
                await loadActiveServices();
            } catch (error) {
                console.error('Gagal membuat kode akses pelanggan', error);
                alert(error.message || 'Terjadi kesalahan saat membuat kode akses.');
            } finally {
                button.disabled = false;
            }
        }

        async function copyCustomerAccessCode(code, button) {
            try {
                await navigator.clipboard.writeText(code);
                const originalLabel = button.textContent;
                button.textContent = 'Tersalin';
                window.setTimeout(() => { button.textContent = originalLabel; }, 1600);
            } catch (error) {
                console.error('Gagal menyalin kode akses pelanggan', error);
                alert(`Kode akses gagal disalin. Silakan salin secara manual: ${code}`);
            }
        }

        function showServiceSuccessModal(data, payload) {
            const device = `${payload.jenis_unit} - ${payload.merek_tipe}`;
            document.getElementById('successServiceNumber').textContent = `Kode akses servis: ${data.no_nota}`;
            document.getElementById('successCustomerName').textContent = payload.nama;
            document.getElementById('successDevice').textContent = device;
            document.getElementById('successAccessCode').textContent = data.customer_token || 'Kode tidak tersedia';

            const copyButton = document.getElementById('successCopyButton');
            const whatsappLink = document.getElementById('successWhatsappLink');
            copyButton.textContent = 'Salin Kode';
            if (data.customer_token) {
                copyButton.disabled = false;
                copyButton.classList.remove('cursor-not-allowed', 'opacity-50');
                copyButton.onclick = () => copyCustomerAccessCode(data.customer_token, copyButton);
                if (formatNomorWA(payload.no_wa)) {
                    whatsappLink.href = buatLinkWA(payload.no_wa, payload.nama, device, data.customer_token);
                    whatsappLink.classList.remove('cursor-not-allowed', 'opacity-50');
                    whatsappLink.removeAttribute('aria-disabled');
                    whatsappLink.title = '';
                    whatsappLink.onclick = null;
                } else {
                    whatsappLink.removeAttribute('href');
                    tandaiLinkWATidakTersedia(whatsappLink, 'Nomor WhatsApp pelanggan tidak tersedia');
                }
            } else {
                copyButton.disabled = true;
                copyButton.classList.add('cursor-not-allowed', 'opacity-50');
                copyButton.onclick = null;
                whatsappLink.removeAttribute('href');
                tandaiLinkWATidakTersedia(whatsappLink, 'Kode akses tidak tersedia untuk dikirim');
            }

            const modal = document.getElementById('serviceSuccessModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeServiceSuccessModal() {
            const modal = document.getElementById('serviceSuccessModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Modal Pelunasan Handler
        function openModalPelunasan(id, noNota, encNama, encUnit, totalBiaya, statusBayar) {
            const nama = decodeURIComponent(encNama);
            const unit = decodeURIComponent(encUnit);

            document.getElementById('modal_service_id').value = id;
            document.getElementById('modal_no_nota').innerText = noNota;
            document.getElementById('modal_nama_pelanggan').innerText = nama;
            document.getElementById('modal_unit').innerText = unit;
            document.getElementById('modal_total_biaya').innerText = `Rp ${Number(totalBiaya).toLocaleString('id-ID')}`;
            document.getElementById('modal_catatan').value = '';

            const badgeSpan = document.getElementById('modal_status_bayar_badge');
            if (statusBayar === 'lunas') {
                badgeSpan.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300';
                badgeSpan.innerText = 'Lunas';
            } else if (statusBayar === 'dp') {
                badgeSpan.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300';
                badgeSpan.innerText = 'DP';
            } else {
                badgeSpan.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300';
                badgeSpan.innerText = 'Belum Bayar';
            }

            document.getElementById('modalPelunasan').classList.remove('hidden');
        }

        function closeModalPelunasan() {
            document.getElementById('modalPelunasan').classList.add('hidden');
        }

        async function submitPelunasan(e) {
            e.preventDefault();
            const serviceId = document.getElementById('modal_service_id').value;
            const metode = document.getElementById('modal_metode_pembayaran').value;
            const catatan = document.getElementById('modal_catatan').value;

            try {
                const res = await fetch('/api/admin/service/pelunasan', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_id: serviceId,
                        metode_pembayaran: metode,
                        catatan: catatan
                    })
                });

                const data = await res.json();
                if (data.status === 'success') {
                    alert(data.message);
                    closeModalPelunasan();
                    loadActiveServices();
                } else {
                    alert(`Gagal: ${data.message}`);
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memproses pelunasan.');
            }
        }

        let technicianWorkloads = [];

        async function saveServiceAssignment(serviceId, technicianId, priority) {
            try {
                const response = await fetch('/api/admin/service/assign-teknisi', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_id: serviceId,
                        teknisi_id: technicianId || null,
                        priority,
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Penugasan gagal disimpan.');
                await Promise.all([loadTechnicianWorkloads(false), loadActiveServices()]);
            } catch (error) {
                alert(error.message);
            }
        }

        async function simpanServis() {
            const intakePhotos = document.getElementById('intake_photos').files;
            const payload = {
                customer_id: document.getElementById('customer_id').value || null,
                nama: document.getElementById('nama').value,
                no_wa: document.getElementById('no_wa').value,
                jenis_unit: document.getElementById('jenis_unit').value,
                merek_tipe: document.getElementById('merek_tipe').value,
                no_seri: document.getElementById('no_seri').value,
                keluhan: document.getElementById('keluhan').value,
                kelengkapan: document.getElementById('kelengkapan').value,
                accessories: Array.from(document.querySelectorAll('input[name="accessories"]:checked')).map(input => input.value),
                physical_condition: document.getElementById('physical_condition').value,
                estimated_completion_at: document.getElementById('estimated_completion_at').value || null,
                priority: document.getElementById('priority').value,
                teknisi_id: document.getElementById('teknisi_id').value || null,
                warranty_days: Number(document.getElementById('warranty_days').value || 0)
            };

            if (!payload.nama || !payload.no_wa || !payload.merek_tipe || !payload.keluhan) {
                return alert('Lengkapi data pendaftaran wajib!');
            }

            const res = await fetch('/api/admin/service', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.status === 'success') {
                let photoError = '';
                if (intakePhotos.length) {
                    try {
                        const photoData = new FormData();
                        photoData.append('service_id', data.service_id);
                        photoData.append('kind', 'intake');
                        Array.from(intakePhotos).forEach(file => photoData.append('photos[]', file));
                        const photoResponse = await fetch('/api/admin/service/photos', { method: 'POST', body: photoData });
                        const photoResult = await photoResponse.json();
                        if (!photoResponse.ok) throw new Error(photoResult.message || 'Foto masuk gagal diunggah.');
                    } catch (error) {
                        photoError = `Servis tersimpan, tetapi foto masuk gagal diunggah: ${error.message}`;
                    }
                }
                if (data.service_id && data.customer_token) {
                    sessionCustomerAccessCodes.set(String(data.service_id), data.customer_token);
                }
                document.getElementById('formPendaftaran').reset();
                document.getElementById('customer_id').value = '';
                document.getElementById('cariPelangganLama').value = '';
                document.getElementById('hasilPencarianPelanggan').classList.add('hidden');
                await Promise.all([loadActiveServices(), loadCustomers(), loadTechnicianWorkloads(false)]);
                showServiceSuccessModal(data, payload);
                if (photoError) alert(photoError);
            } else {
                alert(`Gagal: ${data.message}`);
            }
        }

        async function loadCustomers(page = 1) {
            customerPage = page;
            const q = document.getElementById('cariPelanggan')?.value.trim() || '';
            const tbody = document.getElementById('customersTable');
            const message = document.getElementById('customersMessage');
            tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-400">Memuat data pelanggan…</td></tr>';
            message.textContent = '';

            try {
                const params = new URLSearchParams();
                if (q) params.set('q', q);
                params.set('page', String(customerPage));
                params.set('per_page', '20');
                const sort = document.getElementById('customerSort').value.split(':');
                params.set('sort', sort[0]);
                params.set('direction', sort[1]);
                const status = document.getElementById('customerStatusFilter').value;
                const technicianId = document.getElementById('customerTechnicianFilter').value;
                const dateFrom = document.getElementById('customerDateFrom').value;
                const dateTo = document.getElementById('customerDateTo').value;
                if (status) params.set('status', status);
                if (technicianId) params.set('teknisi_id', technicianId);
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);
                const response = await fetch(`/api/admin/customers${params.size ? `?${params}` : ''}`);
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Daftar pelanggan gagal dimuat.');

                tbody.replaceChildren();
                if (!result.data.length) {
                    const row = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = 5;
                    cell.className = 'p-6 text-center text-slate-400';
                    cell.textContent = 'Belum ada data pelanggan.';
                    row.appendChild(cell);
                    tbody.appendChild(row);
                    renderTablePagination('customerPagination', result.meta, loadCustomers);
                    return;
                }

                result.data.forEach(customer => {
                    const row = document.createElement('tr');
                    row.id = `customer-row-${customer.id}`;
                    row.className = 'hover:bg-amber-50/40 transition-colors';
                    const addCell = (value, className = 'p-3.5 text-slate-700') => {
                        const cell = document.createElement('td');
                        cell.className = className;
                        cell.textContent = value;
                        row.appendChild(cell);
                        return cell;
                    };

                    addCell(customer.nama || '-', 'p-3.5 font-bold text-slate-900');
                    addCell(customer.no_wa || '-', 'p-3.5 text-slate-600 font-mono whitespace-nowrap');
                    addCell(`${Number(customer.jumlah_servis) || 0} unit`, 'p-3.5 text-center font-bold text-[#0B1E3F]');
                    const createdAt = customer.created_at ? new Date(customer.created_at) : null;
                    addCell(createdAt && !Number.isNaN(createdAt.getTime()) ? createdAt.toLocaleDateString('id-ID') : '-', 'p-3.5 text-slate-500 whitespace-nowrap');

                    const actionCell = addCell('', 'p-3.5 text-right whitespace-nowrap');
                    const actions = document.createElement('div');
                    actions.className = 'flex flex-wrap items-center justify-end gap-2';
                    const historyButton = document.createElement('button');
                    historyButton.type = 'button';
                    historyButton.className = 'inline-flex min-h-9 items-center justify-center rounded-xl border border-amber-400/80 bg-amber-50 px-3.5 py-1.5 text-xs font-bold text-[#0B1E3F] transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400';
                    historyButton.textContent = '📋 Riwayat Servis';
                    historyButton.addEventListener('click', () => openRiwayatPelanggan(customer));
                    actions.appendChild(historyButton);

                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-400';
                    editButton.textContent = '✏️ Edit';
                    editButton.addEventListener('click', () => openEditPelanggan(customer));
                    actions.appendChild(editButton);

                    const deleteButton = document.createElement('button');
                    deleteButton.type = 'button';
                    deleteButton.className = 'inline-flex min-h-9 items-center justify-center rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-400';
                    deleteButton.textContent = '🗑️ Hapus';
                    deleteButton.addEventListener('click', () => hapusPelanggan(customer.id));
                    actions.appendChild(deleteButton);
                    actionCell.appendChild(actions);
                    tbody.appendChild(row);
                });
                renderTablePagination('customerPagination', result.meta, loadCustomers);
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-rose-600 font-semibold">Data pelanggan gagal dimuat.</td></tr>';
                message.textContent = error.message || 'Terjadi kesalahan saat memuat pelanggan.';
                document.getElementById('customerPagination').textContent = 'Periksa koneksi dan coba muat ulang.';
            }
        }

        function openEditPelanggan(customer) {
            document.getElementById('editPelangganId').value = customer.id;
            document.getElementById('editPelangganNama').value = customer.nama || '';
            document.getElementById('editPelangganWa').value = customer.no_wa || '';
            document.getElementById('editPelangganAlamat').value = customer.alamat || '';
            document.getElementById('editPelangganError').textContent = '';
            document.getElementById('modalEditPelanggan').classList.remove('hidden');
            document.getElementById('modalEditPelanggan').classList.add('flex');
            document.getElementById('editPelangganNama').focus();
        }

        function closeEditPelanggan() {
            document.getElementById('modalEditPelanggan').classList.add('hidden');
            document.getElementById('modalEditPelanggan').classList.remove('flex');
            document.getElementById('editPelangganError').textContent = '';
        }

        function openRiwayatPelanggan(customer) {
            document.getElementById('riwayatPelangganTitle').textContent = customer.nama || 'Pelanggan';
            document.getElementById('riwayatPelangganKontak').textContent = `WhatsApp: ${customer.no_wa || '-'} • ${Number(customer.jumlah_servis) || 0} riwayat servis`;
            const container = document.getElementById('riwayatPelangganIsi');
            container.replaceChildren();
            const history = Array.isArray(customer.riwayat_servis) ? customer.riwayat_servis : [];
            if (!history.length) {
                container.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada riwayat servis.</div>';
            } else {
                history.forEach(service => {
                    const card = document.createElement('article');
                    card.className = 'rounded-xl border border-slate-200 p-4 text-sm';
                    const unit = [service.jenis_unit, service.merek_tipe].filter(Boolean).join(' • ');
                    const biaya = Number(service.total_biaya || 0).toLocaleString('id-ID');
                    card.innerHTML = `
                        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-3 sm:flex-row">
                            <div>
                                <p class="font-mono font-bold text-[#0B1E3F]">${service.no_nota || '-'}</p>
                                <p class="mt-1 font-bold text-slate-800">${unit || '-'}</p>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="font-extrabold text-[#0B1E3F]">Rp ${biaya}</p>
                                <p class="mt-1 text-xs font-bold uppercase text-slate-500">${service.status || '-'} • ${service.status_pembayaran || '-'}</p>
                            </div>
                        </div>
                        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                            <div><dt class="font-bold text-slate-500">Nomor Seri</dt><dd class="mt-1 text-slate-700">${service.no_seri || '-'}</dd></div>
                            <div><dt class="font-bold text-slate-500">Keluhan</dt><dd class="mt-1 text-slate-700">${service.keluhan || '-'}</dd></div>
                        </dl>`;
                    container.appendChild(card);
                });
            }
            document.getElementById('modalRiwayatPelanggan').classList.remove('hidden');
            document.getElementById('modalRiwayatPelanggan').classList.add('flex');
        }

        function closeRiwayatPelanggan() {
            document.getElementById('modalRiwayatPelanggan').classList.add('hidden');
            document.getElementById('modalRiwayatPelanggan').classList.remove('flex');
        }

        async function simpanEditPelanggan(event) {
            event.preventDefault();
            const id = document.getElementById('editPelangganId').value;
            const button = document.getElementById('savePelangganButton');
            const error = document.getElementById('editPelangganError');
            const message = document.getElementById('customersMessage');
            const payload = {
                nama: document.getElementById('editPelangganNama').value.trim(),
                no_wa: document.getElementById('editPelangganWa').value.trim(),
                alamat: document.getElementById('editPelangganAlamat').value.trim()
            };

            button.disabled = true;
            error.textContent = '';
            try {
                const response = await fetch(`/api/admin/customers/${encodeURIComponent(id)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Data pelanggan gagal diperbarui.');
                closeEditPelanggan();
                await Promise.all([loadCustomers(), loadActiveServices()]);
                message.className = 'text-sm font-bold text-emerald-700';
                message.textContent = result.message;
            } catch (updateError) {
                error.textContent = updateError.message || 'Terjadi kesalahan saat memperbarui data pelanggan.';
            } finally {
                button.disabled = false;
            }
        }

        async function hapusPelanggan(id) {
            const konfirmasi = confirm('Hapus pelanggan dari daftar aktif? Riwayat servis akan tetap aman dan dapat dipulihkan.');
            if (!konfirmasi) return;

            const message = document.getElementById('customersMessage');
            try {
                const response = await fetch(`/api/admin/customers/${encodeURIComponent(id)}`, { method: 'DELETE' });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Data pelanggan gagal dihapus.');
                document.getElementById(`customer-row-${id}`)?.remove();
                await Promise.all([loadCustomers(), loadActiveServices()]);
                message.className = 'text-sm font-bold text-emerald-700';
                message.textContent = result.message;
            } catch (error) {
                message.className = 'text-sm font-bold text-rose-700';
                message.textContent = error.message || 'Terjadi kesalahan saat menghapus data pelanggan.';
            }
        }

        async function hapusServis(id, noNota) {
            const konfirmasi = confirm(`Yakin ingin menghapus permanen servis ${noNota} beserta detail dan log terkait?`);
            if (!konfirmasi) return;

            try {
                const response = await fetch(`/api/admin/service/${encodeURIComponent(id)}`, { method: 'DELETE' });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Data servis gagal dihapus.');
                document.getElementById(`service-row-${id}`)?.remove();
                await Promise.all([loadActiveServices(), loadCustomers()]);
                alert(result.message);
            } catch (error) {
                console.error('Gagal menghapus data servis', error);
                alert(error.message || 'Terjadi kesalahan saat menghapus data servis.');
            }
        }

        let pencarianPelangganTimeout;
        document.getElementById('cariPelangganLama').addEventListener('input', event => {
            clearTimeout(pencarianPelangganTimeout);
            const query = event.target.value.trim();
            if (query.length < 2) {
                document.getElementById('hasilPencarianPelanggan').classList.add('hidden');
                return;
            }
            pencarianPelangganTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`/api/admin/customers?q=${encodeURIComponent(query)}&limit=20`);
                    const result = await response.json();
                    const container = document.getElementById('hasilPencarianPelanggan');
                    container.replaceChildren();
                    if (!response.ok || result.status !== 'success' || !result.data.length) {
                        container.innerHTML = '<div class="p-3 text-sm text-slate-500">Pelanggan belum ditemukan.</div>';
                        container.classList.remove('hidden');
                        return;
                    }
                    result.data.forEach(customer => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'flex w-full items-center justify-between gap-3 border-b border-slate-100 px-3.5 py-3 text-left text-sm transition last:border-0 hover:bg-amber-50';
                        button.innerHTML = `<span><strong class="block text-[#0B1E3F]">${customer.nama}</strong><span class="text-xs text-slate-500">${customer.no_wa || '-'}</span></span><span class="text-xs font-bold text-slate-500">${customer.jumlah_servis || 0} servis</span>`;
                        button.addEventListener('click', () => {
                            document.getElementById('customer_id').value = customer.id;
                            document.getElementById('nama').value = customer.nama || '';
                            document.getElementById('no_wa').value = customer.no_wa || '';
                            document.getElementById('cariPelangganLama').value = `${customer.nama} • ${customer.no_wa || '-'}`;
                            container.classList.add('hidden');
                        });
                        container.appendChild(button);
                    });
                    container.classList.remove('hidden');
                } catch (error) {
                    console.error('Pencarian pelanggan gagal', error);
                }
            }, 250);
        });

        document.getElementById('nama').addEventListener('input', () => {
            document.getElementById('customer_id').value = '';
        });
        document.getElementById('no_wa').addEventListener('input', () => {
            document.getElementById('customer_id').value = '';
        });

        let pencarianDaftarTimeout;
        document.getElementById('cariPelanggan').addEventListener('input', () => {
            clearTimeout(pencarianDaftarTimeout);
            pencarianDaftarTimeout = setTimeout(() => loadCustomers(1), 250);
        });
        ['serviceSearch', 'serviceStatusFilter', 'serviceTechnicianFilter', 'serviceDateFrom', 'serviceDateTo', 'serviceSort']
            .forEach(id => document.getElementById(id).addEventListener('input', () => loadActiveServices(1)));
        ['customerStatusFilter', 'customerTechnicianFilter', 'customerDateFrom', 'customerDateTo', 'customerSort']
            .forEach(id => document.getElementById(id).addEventListener('change', () => loadCustomers(1)));

        // Inisialisasi data saat halaman dibuka
        loadActiveServices();
        loadCustomers();
        async function loadTechnicianWorkloads(refreshServices = true) {
            const select = document.getElementById('teknisi_id');
            try {
                const response = await fetch('/api/admin/teknisi');
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Beban teknisi gagal dimuat.');
                technicianWorkloads = result.data;
                ['serviceTechnicianFilter', 'customerTechnicianFilter'].forEach(id => {
                    const filter = document.getElementById(id);
                    filter.replaceChildren(new Option('Semua teknisi', ''));
                    technicianWorkloads.forEach(technician => filter.add(new Option(technician.nama, technician.id)));
                });
                technicianWorkloads.forEach(technician => {
                    const option = document.createElement('option');
                    option.value = technician.id;
                    option.textContent = `${technician.nama} · ${technician.beban_kerja} servis aktif`;
                    select.append(option);
                });
                if (refreshServices) await loadActiveServices();
            } catch (error) {
                console.error(error);
                select.innerHTML = '<option value="">Beban teknisi gagal dimuat</option>';
            }
        }
        loadTechnicianWorkloads();
        window.addEventListener('load', () => {
            if (!window.ServiceRealtime) return;
            window.ServiceRealtime.startStaff('admin', () => {
                loadActiveServices();
            });
        });
        window.addEventListener('panel-notification', () => loadActiveServices());
    </script>
    <script src="/panel-notifications.js"></script>
    <script src="/account-menu.js" data-role="admin"></script>
</body>

</html>
