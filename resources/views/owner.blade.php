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
    <title>Panel Owner - TECKFO Service</title>
    @include('partials.realtime-scripts')
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
    <style>
        .owner-account-slot .account-bar {
            gap: 8px;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
        }
        .owner-account-slot .account-name {
            max-width: 180px;
            overflow: hidden;
            color: #0B1E3F;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .owner-account-slot .account-bar button {
            border: 1px solid #D4AF37;
            background: #0B1E3F;
        }
        .owner-account-slot .account-bar button.secondary {
            background: #fff;
            color: #0B1E3F;
        }
        @media (max-width: 640px) {
            .owner-account-slot .account-bar { gap: 5px; }
            .owner-account-slot .account-name { max-width: 82px; font-size: 11px; }
            .owner-account-slot .account-bar button { padding: 7px; font-size: 11px; }
        }
    </style>
</head>

<body data-theme="dashboard" class="min-h-screen flex bg-slate-50 font-sans text-slate-900">
    <button id="ownerSidebarOverlay" type="button" class="fixed inset-0 z-40 hidden bg-slate-950/60 md:hidden" aria-label="Tutup menu"></button>

    <aside id="ownerSidebar" class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 -translate-x-full flex-col border-r-2 border-amber-400 bg-[#0B1E3F] text-white transition-transform duration-300 md:sticky md:top-0 md:translate-x-0">
        <div class="border-b border-amber-400/40 px-6 py-6">
            <a href="/owner.html" class="flex items-center gap-3 text-white no-underline">
                <span class="grid h-11 w-11 place-items-center rounded-xl border border-amber-400/70 bg-white">
                    <img src="{{ asset('teckfo-logo.png') }}" alt="Logo Teckfo Komputer" class="h-9 w-9 rounded-lg object-contain">
                </span>
                <span>
                    <strong class="block text-sm font-extrabold tracking-wide">TECKFO SERVICE</strong>
                    <small class="mt-1 block text-[11px] text-slate-300">Owner Workspace</small>
                </span>
            </a>
        </div>
        <nav class="space-y-2 px-3 py-6" aria-label="Navigasi Owner">
            <button type="button" data-owner-nav="dashboard" aria-current="page" class="flex w-full items-center gap-3 border-l-4 border-amber-400 bg-[#153266] px-4 py-3 text-left text-sm font-semibold text-amber-300 transition hover:bg-[#153266]">
                <span aria-hidden="true">📊</span> Dashboard &amp; Laporan
            </button>
            <button type="button" data-owner-nav="staff" class="flex w-full items-center gap-3 border-l-4 border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition hover:bg-[#132F63] hover:text-white">
                <span aria-hidden="true">👥</span> Manajemen Staf
            </button>
            <button type="button" data-owner-nav="inventory" class="flex w-full items-center gap-3 border-l-4 border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition hover:bg-[#132F63] hover:text-white">
                <span aria-hidden="true">📦</span> Stok Sparepart
            </button>
            <a href="/owner/activity-logs" class="flex w-full items-center gap-3 border-l-4 border-transparent px-4 py-3 text-sm font-medium text-slate-200 no-underline transition hover:bg-[#132F63] hover:text-white">
                <span aria-hidden="true">🧾</span> Log Aktivitas
            </a>
            <a href="/owner/notifications" class="flex w-full items-center gap-3 border-l-4 border-transparent px-4 py-3 text-sm font-medium text-slate-200 no-underline transition hover:bg-[#132F63] hover:text-white">
                <span aria-hidden="true">💬</span> Notifikasi WhatsApp
            </a>
        </nav>
        <div class="mt-auto border-t border-amber-400/30 px-5 py-4 text-[11px] text-slate-400">
            Manajemen servis &amp; inventaris
        </div>
    </aside>

    <div id="ownerAppContent" class="flex min-h-screen min-w-0 flex-1 flex-col">
        <header class="owner-topbar sticky top-0 z-30 flex min-h-20 items-center gap-4 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6">
            <button id="ownerSidebarToggle" type="button" class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-[#0B1E3F] shadow-sm md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="ownerSidebar">
                ☰
            </button>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[.16em] text-amber-700">Panel Eksekutif</p>
                <h1 id="activeSectionTitle" class="truncate text-base font-extrabold text-[#0B1E3F] sm:text-lg">Dashboard &amp; Laporan</h1>
            </div>
            <div id="ownerAccountSlot" class="owner-account-slot"></div>
        </header>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            <div data-owner-panel="dashboard">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#0B1E3F] text-amber-400 mb-2 border border-amber-400/40">
                        <img src="{{ asset('teckfo-logo.png') }}" alt="Logo Teckfo Komputer" class="h-5 w-5 rounded object-contain">
                        Executive Dashboard &amp; Oversight
                    </span>
                    <h1 class="text-2xl font-extrabold text-[#0B1E3F] tracking-tight">Panel Eksekutif Owner</h1>
                    <p class="text-xs text-slate-500 mt-1">Pantau analitik servis, omzet pendapatan, kelola akun staf, dan catat pengadaan sparepart supplier</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <button onclick="openModalPengadaan()"
                        class="bg-[#0B1E3F] hover:bg-[#132F63] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition border border-amber-400/80 shadow-sm flex items-center gap-2">
                        <span>➕</span> Catat Pengadaan Baru
                    </button>
                    <button onclick="loadAllData()"
                        class="bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold px-4 py-2.5 rounded-xl border border-slate-300 transition shadow-sm">
                        🔄 Refresh
                    </button>
                </div>
            </div>
            </div>

            <!-- Pengaturan akun staf, hanya tersedia untuk owner -->
            <section data-owner-panel="staff" hidden class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400 space-y-5">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3.5">
                        <span class="w-11 h-11 bg-[#0B1E3F] text-amber-400 border border-amber-400/50 rounded-xl flex items-center justify-center text-xl shadow-sm font-bold">👥</span>
                        <div>
                            <h2 class="text-xl font-bold text-[#0B1E3F]">Kelola Akun Admin & Teknisi</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Owner memiliki otoritas penuh menambah, memperbarui data, dan menghapus akun staf.</p>
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <button id="openAddStaffModalBtn" type="button" class="bg-[#0B1E3F] hover:bg-[#132F63] text-white text-xs font-bold px-4 py-2.5 rounded-xl transition border border-amber-400/80 shadow-sm flex items-center gap-1.5">
                            <span>➕</span> Tambah Staf Baru
                        </button>
                        <button id="reloadStaffAccounts" type="button" class="bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold px-3.5 py-2.5 rounded-xl border border-slate-300 transition shadow-sm">
                            🔄 Muat Ulang Akun
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[#0B1E3F] text-white border-b-2 border-amber-400 uppercase tracking-wider text-[11px] font-semibold">
                            <tr>
                                <th class="p-3.5">Nama Lengkap</th>
                                <th class="p-3.5">Peran / Role</th>
                                <th class="p-3.5">Username</th>
                                <th class="p-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="staffAccountRows" class="divide-y divide-slate-100 bg-white">
                            <tr><td colspan="4" class="p-6 text-center text-slate-400">Memuat akun staf…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <input id="staffSearch" type="search" placeholder="Cari nama atau username" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select id="staffRoleFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua peran</option><option value="admin">Admin</option><option value="teknisi">Teknisi</option>
                    </select>
                    <select id="staffSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="nama:asc">Nama A–Z</option><option value="nama:desc">Nama Z–A</option><option value="role:asc">Peran</option>
                    </select>
                </div>
                <div id="staffPagination" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600" aria-live="polite"></div>
                <p id="staffAccountMessage" class="text-sm font-bold text-slate-600" role="status" aria-live="polite"></p>
            </section>

            <!-- Modal Tambah Akun Staf Baru -->
            <div id="addStaffModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[60] hidden items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
                    <div class="flex justify-between items-center bg-[#0B1E3F] text-white px-6 py-4 border-b-2 border-amber-400">
                        <div>
                            <h2 class="text-base font-bold">Tambah Akun Staf Baru</h2>
                            <p class="text-xs text-slate-300 mt-0.5">Daftarkan akun Admin atau Teknisi baru untuk sistem servis</p>
                        </div>
                        <button type="button" id="closeAddStaffModalTopBtn" class="text-slate-300 hover:text-white text-2xl font-bold leading-none">&times;</button>
                    </div>
                    <form id="addStaffForm" class="space-y-4 p-6" autocomplete="off">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nama Lengkap Staf <span class="text-rose-500">*</span></label>
                            <input id="addStaffNama" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="2" maxlength="100" required placeholder="Contoh: Budi Santoso">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                                <input id="addStaffUsername" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="3" maxlength="32" required placeholder="budi_teknisi">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Peran / Role <span class="text-rose-500">*</span></label>
                                <select id="addStaffRole" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 font-semibold" required>
                                    <option value="teknisi" selected>Teknisi</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password <span class="text-rose-500">*</span></label>
                            <input id="addStaffPassword" type="password" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="8" required placeholder="Minimal 8 karakter">
                        </div>
                        <p id="addStaffMessage" class="min-h-5 text-xs text-rose-600 font-bold" role="alert" aria-live="polite"></p>
                        <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
                            <button id="cancelAddStaffBtn" type="button" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition border border-slate-300">Batal</button>
                            <button type="submit" id="saveAddStaffBtn" class="px-5 py-2.5 rounded-xl bg-[#0B1E3F] hover:bg-[#132F63] border border-amber-400/80 text-white text-xs font-bold transition shadow-sm">Simpan Akun</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Edit Akun Staf -->
            <div id="staffAccountModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[60] hidden items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
                    <div class="flex justify-between items-center bg-[#0B1E3F] text-white px-6 py-4 border-b-2 border-amber-400">
                        <div>
                            <h2 class="text-base font-bold">Edit Akun Staf</h2>
                            <p id="staffAccountIdentity" class="text-xs text-amber-400 font-bold mt-0.5"></p>
                        </div>
                        <button type="button" id="closeStaffModalTopBtn" class="text-slate-300 hover:text-white text-2xl font-bold leading-none">&times;</button>
                    </div>
                    <form id="staffAccountForm" class="space-y-4 p-6" autocomplete="off">
                        <input id="staffAccountId" type="hidden">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nama Lengkap Staf <span class="text-rose-500">*</span></label>
                            <input id="staffEditNama" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="2" maxlength="100" required placeholder="Contoh: Budi Santoso">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                            <input id="staffEditUsername" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="3" maxlength="32" required placeholder="Contoh: budi_teknisi">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password Baru <span class="font-normal text-slate-400">(Opsional / kosongkan jika tidak diubah)</span></label>
                            <input id="staffEditPassword" type="password" class="w-full border border-slate-300 rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter">
                        </div>
                        <p id="staffEditMessage" class="min-h-5 text-xs text-rose-600 font-bold" role="alert" aria-live="polite"></p>
                        <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
                            <button id="cancelStaffEdit" type="button" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition border border-slate-300">Batal</button>
                            <button type="submit" id="saveStaffBtn" class="px-5 py-2.5 rounded-xl bg-[#0B1E3F] hover:bg-[#132F63] border border-amber-400/80 text-white text-xs font-bold transition shadow-sm">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div data-owner-panel="dashboard">
            <!-- Summary KPI & Rekap Keuangan Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400 space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Total Servis Masuk</span>
                        <span class="p-2 bg-blue-50 text-[#0B1E3F] rounded-xl text-base">📋</span>
                    </div>
                    <div id="totalUnit" class="text-3xl font-black text-[#0B1E3F]">0</div>
                    <div class="text-xs text-slate-500">Unit servis terdaftar di sistem</div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-emerald-500 space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Unit Selesai / Diambil</span>
                        <span class="p-2 bg-emerald-50 text-emerald-700 rounded-xl text-base">✅</span>
                    </div>
                    <div id="unitSelesai" class="text-3xl font-black text-emerald-600">0</div>
                    <div class="text-xs text-slate-500">Pengerjaan telah tuntas & diserahkan</div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-blue-600 space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Total Pendapatan (Omzet Lunas)</span>
                        <span class="p-2 bg-blue-50 text-blue-700 rounded-xl text-base">💵</span>
                    </div>
                    <div id="totalPendapatan" class="text-3xl font-black text-[#0B1E3F]">Rp 0</div>
                    <div class="text-xs text-slate-500">Total kas masuk dari servis lunas</div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-rose-500 space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Pengeluaran Pengadaan Sparepart</span>
                        <span class="p-2 bg-rose-50 text-rose-700 rounded-xl text-base">🛒</span>
                    </div>
                    <div id="totalPengadaan" class="text-3xl font-black text-rose-700">Rp 0</div>
                    <div id="totalTransaksiPengadaan" class="text-xs text-slate-500">0 transaksi pembelian</div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-500 space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Laba Kotor Servis</span>
                        <span class="p-2 bg-amber-50 text-amber-800 rounded-xl text-base">📈</span>
                    </div>
                    <div id="labaBersih" class="text-3xl font-black text-emerald-600">Rp 0</div>
                    <div class="text-xs text-slate-500">Total tagihan servis dikurangi modal sparepart terpakai</div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-[#0B1E3F] space-y-1.5 transition hover:shadow-md">
                    <div class="flex justify-between items-center text-slate-500 text-xs font-bold uppercase tracking-wider">
                        <span>Nilai Aset Stok Onderdil</span>
                        <span class="p-2 bg-amber-50 text-amber-700 rounded-xl text-base">📦</span>
                    </div>
                    <div id="nilaiAsetStok" class="text-3xl font-black text-amber-700">Rp 0</div>
                    <div id="totalStokBarang" class="text-xs text-slate-500">0 total unit onderdil tersedia</div>
                </div>
            </div>

            <!-- Breakdown Status Servis -->
            <div class="bg-white p-7 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400 space-y-4">
                <h2 class="text-base font-bold text-[#0B1E3F]">Distribusi Status Servis Saat Ini</h2>
                <div id="statusList" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 text-center">
                    <!-- Diisi via JS -->
                </div>
            </div>
            <section class="space-y-5 rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="reportTitle">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="reportTitle" class="text-lg font-extrabold text-[#0B1E3F]">Laporan Owner</h2>
                        <p class="mt-1 text-xs text-slate-500">Omzet tagihan berdasarkan tanggal servis, bukan tanggal transaksi pembayaran.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="reportCsv" type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold">Ekspor CSV</button>
                        <button id="reportPdf" type="button" class="rounded-lg bg-[#0B1E3F] px-3 py-2 text-xs font-bold text-white">Cetak / Simpan PDF</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-xs font-semibold text-slate-600">Dari tanggal<input id="reportDateFrom" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold text-slate-600">Sampai tanggal<input id="reportDateTo" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="text-xs font-semibold text-slate-600">Kelompok grafik<select id="reportPeriod" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="day">Harian</option><option value="month">Bulanan</option></select></label>
                    <button id="loadReport" type="button" class="self-end rounded-lg border border-amber-400 bg-amber-400 px-4 py-2 text-sm font-bold text-[#0B1E3F]">Tampilkan laporan</button>
                </div>
                <p id="reportMessage" class="text-sm" role="status" aria-live="polite"></p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Omzet servis</p><p id="reportRevenue" class="mt-1 font-extrabold text-[#0B1E3F]">—</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Modal sparepart terpakai</p><p id="reportCost" class="mt-1 font-extrabold text-slate-800">—</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Laba kotor (omzet - modal)</p><p id="reportProfit" class="mt-1 font-extrabold text-emerald-700">—</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Piutang saat ini pada periode</p><p id="reportReceivable" class="mt-1 font-extrabold text-rose-700">—</p></div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                    <svg id="reportChart" class="h-56 min-w-[640px] w-full" viewBox="0 0 800 240" role="img" aria-label="Grafik omzet servis"></svg>
                </div>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <h3 class="p-3 text-sm font-bold text-[#0B1E3F]">Servis per status</h3>
                        <table class="w-full text-left text-xs"><thead class="bg-slate-100"><tr><th class="p-2">Status</th><th class="p-2 text-right">Jumlah</th></tr></thead><tbody id="reportStatusRows"></tbody></table>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <h3 class="p-3 text-sm font-bold text-[#0B1E3F]">Servis per teknisi</h3>
                        <table class="w-full text-left text-xs"><thead class="bg-slate-100"><tr><th class="p-2">Teknisi</th><th class="p-2 text-right">Servis</th><th class="p-2 text-right">Omzet</th></tr></thead><tbody id="reportTechnicianRows"></tbody></table>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <h3 class="p-3 text-sm font-bold text-[#0B1E3F]">Sparepart terlaris</h3>
                        <table class="w-full text-left text-xs"><thead class="bg-slate-100"><tr><th class="p-2">Sparepart</th><th class="p-2 text-right">Terjual</th></tr></thead><tbody id="reportPartsRows"></tbody></table>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <h3 class="p-3 text-sm font-bold text-[#0B1E3F]">Piutang / belum lunas</h3>
                    <table class="w-full min-w-[720px] text-left text-xs">
                        <thead class="bg-[#0B1E3F] text-white"><tr><th class="p-3">Nota</th><th class="p-3">Pelanggan</th><th class="p-3">Tanggal</th><th class="p-3 text-right">Tagihan</th><th class="p-3 text-right">Piutang</th></tr></thead>
                        <tbody id="reportReceivableRows"></tbody>
                    </table>
                    <div id="reportReceivablePagination" class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm text-slate-600" aria-live="polite"></div>
                </div>
            </section>
            </div>

            <section data-owner-panel="inventory" hidden class="space-y-8">
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-[#0B1E3F]">Inventaris Sparepart</h2>
                        <p class="mt-1 text-sm text-slate-500">Pantau stok tersedia dan lanjutkan ke pengelolaan lengkap atau pencatatan barang masuk.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="/spareparts.html" class="inline-flex items-center justify-center rounded-xl border border-amber-400/80 bg-[#0B1E3F] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#132F63]">Kelola Sparepart</a>
                        <button type="button" onclick="openModalPengadaan()" class="rounded-xl border border-amber-400 bg-amber-400 px-4 py-2.5 text-xs font-bold text-[#0B1E3F] transition hover:bg-amber-300">+ Catat Pengadaan</button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 border-t-4 border-amber-400 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="font-bold text-[#0B1E3F]">Stok Saat Ini</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-3">
                        <input id="ownerPartsSearch" type="search" placeholder="Cari kode atau nama sparepart" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-3">
                        <select id="ownerPartsStockFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Semua stok</option><option value="low">Stok minimum / rendah</option><option value="available">Tersedia</option><option value="out">Habis</option>
                        </select>
                        <select id="ownerPartsSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:col-span-2">
                            <option value="nama_barang:asc">Nama A–Z</option><option value="nama_barang:desc">Nama Z–A</option>
                            <option value="stok:asc">Stok terendah</option><option value="harga_jual:desc">Harga jual tertinggi</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b-2 border-amber-400 bg-[#0B1E3F] text-xs uppercase tracking-wide text-white">
                                <tr>
                                    <th class="p-3.5">Kode Barang</th>
                                    <th class="p-3.5">Nama Sparepart</th>
                                    <th class="p-3.5 text-right">Stok</th>
                                    <th class="p-3.5 text-right">Harga Beli</th>
                                    <th class="p-3.5 text-right">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody id="ownerSparepartsRows" class="divide-y divide-slate-100 bg-white">
                                <tr><td colspan="5" class="p-6 text-center text-slate-400">Memuat inventaris…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="ownerPartsPagination" class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm text-slate-600" aria-live="polite"></div>
                </div>
            </div>
            <!-- Riwayat Pengadaan Sparepart (Purchases & Purchase Details) -->
            <div class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400 space-y-5">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3.5">
                        <span class="w-11 h-11 bg-[#0B1E3F] text-amber-400 border border-amber-400/50 rounded-xl flex items-center justify-center text-xl shadow-sm font-bold">🛒</span>
                        <div>
                            <h2 class="text-xl font-bold text-[#0B1E3F]">Riwayat Pengadaan Barang dari Supplier</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Catatan pembelian sparepart supplier yang otomatis menambah stok fisik toko</p>
                        </div>
                    </div>
                    <button onclick="openModalPengadaan()"
                        class="bg-[#0B1E3F] hover:bg-[#132F63] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition border border-amber-400/80 shadow-sm flex items-center gap-1.5">
                        <span>➕</span> Catat Pengadaan Baru
                    </button>
                </div>

                <!-- Tabel Riwayat Pengadaan -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#0B1E3F] text-white border-b-2 border-amber-400 uppercase tracking-wider text-[11px] font-semibold">
                            <tr>
                                <th class="p-3.5">No. Pembelian</th>
                                <th class="p-3.5">Tanggal</th>
                                <th class="p-3.5">Nama Supplier</th>
                                <th class="p-3.5">Daftar Barang & Jumlah</th>
                                <th class="p-3.5 text-right">Total Biaya Pembelian</th>
                                <th class="p-3.5">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="purchasesTable" class="divide-y divide-slate-100 bg-white">
                            <!-- Diisi via JS -->
                        </tbody>
                    </table>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                    <input id="purchaseSearch" type="search" placeholder="Cari nomor pembelian atau supplier" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">
                    <input id="purchaseDateFrom" type="date" aria-label="Tanggal pengadaan dari" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input id="purchaseDateTo" type="date" aria-label="Tanggal pengadaan sampai" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select id="purchaseSort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="tanggal_pembelian:desc">Tanggal terbaru</option><option value="tanggal_pembelian:asc">Tanggal terlama</option>
                        <option value="total_biaya:desc">Biaya tertinggi</option><option value="supplier:asc">Supplier A–Z</option>
                    </select>
                </div>
                <div id="purchasePagination" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600" aria-live="polite"></div>
            </div>
            </section>
        </div>
    </main>

    <!-- Modal Form Pengadaan Barang Baru (Owner) -->
    <div id="modalPengadaan" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200">
            <!-- Modal Header -->
            <div class="bg-[#0B1E3F] text-white px-6 py-4 flex justify-between items-center border-b-2 border-amber-400">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 bg-amber-500 text-slate-950 rounded-lg text-sm font-bold">🛒</span>
                    <div>
                        <h3 class="text-base font-bold">Pengadaan Sparepart Baru dari Supplier</h3>
                        <p class="text-[11px] text-slate-300">Stok di tabel spareparts otomatis bertambah setelah data disimpan</p>
                    </div>
                </div>
                <button type="button" onclick="closeModalPengadaan()" class="text-slate-300 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Modal Form Body -->
            <form onsubmit="submitPengadaan(event)" class="p-6 space-y-4 overflow-y-auto flex-1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nama Supplier / Distributor <span class="text-rose-500">*</span></label>
                        <input type="text" id="purch_supplier" required placeholder="Contoh: PT Nusantara Komputer Perkasa"
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tanggal Pembelian <span class="text-rose-500">*</span></label>
                        <input type="date" id="purch_tanggal" required
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="p-3.5 bg-amber-50/80 rounded-xl border border-amber-300 text-xs text-amber-950 flex items-center gap-2.5">
                    <span class="text-base flex-shrink-0">⚡</span>
                    <span><strong>Otomasi Sistem:</strong> Jumlah unit yang dibeli akan langsung ditambahkan ke stok tabel <code>spareparts</code> secara otomatis.</span>
                </div>

                <!-- Daftar Item Pembelian -->
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">Daftar Barang yang Dibeli</label>
                        <button type="button" onclick="addItemRow()"
                            class="px-3 py-1.5 bg-[#0B1E3F] hover:bg-[#132F63] text-white text-xs font-bold rounded-lg border border-amber-400/60 transition shadow-sm">
                            + Tambah Item Barang
                        </button>
                    </div>

                    <div id="itemsContainer" class="space-y-3">
                        <!-- Baris item dinamis -->
                    </div>
                </div>

                <!-- Total Estimasi Biaya -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-700">Total Pengeluaran Pengadaan:</span>
                    <span id="purch_total_biaya_display" class="text-base font-black text-[#0B1E3F]">Rp 0</span>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Keterangan / Nomor Faktur Supplier <span class="font-normal text-slate-400">(Opsional)</span></label>
                    <input type="text" id="purch_keterangan" placeholder="Contoh: Faktur No. INV-9901 / Restock RAM & SSD akhir bulan"
                        class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModalPengadaan()"
                        class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-black rounded-xl shadow-md border border-amber-600 transition">
                        Simpan Pengadaan & Tambah Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>

    <script>
        let sparepartsList = [];
        let rowCounter = 0;
        let purchasePage = 1;
        let ownerPartsPage = 1;
        let reportPage = 1;

        function renderOwnerPagination(elementId, meta, onPage) {
            const container = document.getElementById(elementId);
            container.replaceChildren();
            if (!meta || !meta.total) {
                container.textContent = 'Tidak ada data untuk filter ini.';
                return;
            }
            const summary = document.createElement('span');
            const first = (meta.current_page - 1) * meta.per_page + 1;
            summary.textContent = `Menampilkan ${first}–${Math.min(meta.current_page * meta.per_page, meta.total)} dari ${meta.total}`;
            const actions = document.createElement('div');
            actions.className = 'flex gap-2';
            [['Sebelumnya', meta.current_page - 1], ['Berikutnya', meta.current_page + 1]].forEach(([label, page]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.disabled = label === 'Sebelumnya' ? meta.current_page <= 1 : meta.current_page >= meta.last_page;
                button.className = 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold disabled:opacity-40';
                button.addEventListener('click', () => onPage(page));
                actions.append(button);
            });
            container.append(summary, actions);
        }

        function reportFilters(page = 1) {
            const params = new URLSearchParams({ page: String(page), per_page: '20' });
            ['date_from', 'date_to'].forEach(key => {
                const value = document.getElementById(key === 'date_from' ? 'reportDateFrom' : 'reportDateTo').value;
                if (value) params.set(key, value);
            });
            params.set('period', document.getElementById('reportPeriod').value);
            return params;
        }

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        async function loadAllData() {
            await Promise.all([loadDashboard(), loadPurchases(), loadSparepartsCatalog(), loadOwnerParts(), loadOwnerReport()]);
        }

        // 1. Ambil Data Dashboard Owner
        async function loadDashboard() {
            try {
                const res = await fetch('/api/owner/dashboard');
                const result = await res.json();
                if (!res.ok || result.status !== 'success') throw new Error(result.message || 'Dashboard gagal dimuat.');

                if (result.status === 'success') {
                    const { summary, statusCounts, purchases, spareparts } = result.data;

                    document.getElementById('totalUnit').innerText = summary.total_unit || 0;
                    document.getElementById('unitSelesai').innerText = summary.unit_selesai || 0;

                    const nominalPendapatan = summary.total_pendapatan ? parseFloat(summary.total_pendapatan) : 0;
                    document.getElementById('totalPendapatan').innerText = `Rp ${nominalPendapatan.toLocaleString('id-ID')}`;

                    const nominalPengadaan = purchases.total_pengeluaran_pengadaan ? parseFloat(purchases.total_pengeluaran_pengadaan) : 0;
                    document.getElementById('totalPengadaan').innerText = `Rp ${nominalPengadaan.toLocaleString('id-ID')}`;
                    document.getElementById('totalTransaksiPengadaan').innerText = `${purchases.total_transaksi_pengadaan || 0} transaksi pengadaan`;

                    const grossProfit = Number(summary.laba_kotor || 0);
                    const elLaba = document.getElementById('labaBersih');
                    if (grossProfit >= 0) {
                        elLaba.className = 'text-3xl font-black text-emerald-600';
                        elLaba.innerText = `Rp ${grossProfit.toLocaleString('id-ID')}`;
                    } else {
                        elLaba.className = 'text-3xl font-black text-rose-600';
                        elLaba.innerText = `-Rp ${Math.abs(grossProfit).toLocaleString('id-ID')}`;
                    }

                    // Nilai Aset Stok
                    const nilaiAset = spareparts.total_nilai_aset_barang ? parseFloat(spareparts.total_nilai_aset_barang) : 0;
                    document.getElementById('nilaiAsetStok').innerText = `Rp ${nilaiAset.toLocaleString('id-ID')}`;
                    document.getElementById('totalStokBarang').innerText = `${spareparts.total_stok_tersedia || 0} unit (${spareparts.total_jenis_barang || 0} jenis barang)`;

                    // Render Distribusi Status
                    const statusList = document.getElementById('statusList');
                    statusList.innerHTML = '';

                    statusCounts.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'bg-slate-50 p-3 rounded-xl border border-slate-200';
                        card.innerHTML = `
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">${item.status.replace('_', ' ')}</div>
                            <div class="text-xl font-black text-[#0B1E3F] mt-1">${item.jumlah}</div>
                        `;
                        statusList.appendChild(card);
                    });
                }
            } catch (err) {
                console.error('Gagal memuat data dashboard', err);
                document.getElementById('statusList').textContent = err.message || 'Dashboard gagal dimuat.';
            }
        }

        // 2. Ambil Riwayat Pengadaan
        async function loadPurchases(page = 1) {
            purchasePage = page;
            const tbody = document.getElementById('purchasesTable');
            tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-500">Memuat riwayat pengadaan…</td></tr>';
            try {
                const params = new URLSearchParams({
                    page: String(purchasePage),
                    per_page: '20',
                    sort: document.getElementById('purchaseSort').value.split(':')[0],
                    direction: document.getElementById('purchaseSort').value.split(':')[1],
                });
                const search = document.getElementById('purchaseSearch').value.trim();
                const dateFrom = document.getElementById('purchaseDateFrom').value;
                const dateTo = document.getElementById('purchaseDateTo').value;
                if (search) params.set('search', search);
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);
                const res = await fetch(`/api/owner/purchases?${params}`);
                const result = await res.json();
                if (!res.ok || result.status !== 'success') throw new Error(result.message || 'Riwayat pengadaan gagal dimuat.');
                tbody.innerHTML = '';

                if (result.data.length > 0) {
                    result.data.forEach(p => {
                        const totalRp = Number(p.total_biaya || 0).toLocaleString('id-ID');
                        const tgl = new Date(p.tanggal_pembelian).toLocaleDateString('id-ID');

                        let itemsHtml = '<ul class="space-y-1">';
                        if (p.items && p.items.length > 0) {
                            p.items.forEach(it => {
                                const subtotalRp = Number(it.subtotal || 0).toLocaleString('id-ID');
                                const hargaBeliRp = Number(it.harga_beli_satuan || 0).toLocaleString('id-ID');
                                itemsHtml += `
                                    <li class="bg-white p-2 rounded-lg border border-slate-200">
                                        <div class="font-bold text-slate-800">${it.nama_barang}</div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            ${it.jumlah} unit @ Rp ${hargaBeliRp} = <strong class="text-[#0B1E3F]">Rp ${subtotalRp}</strong>
                                            <span class="text-emerald-700 font-bold">(Stok saat ini: ${it.stok_sekarang})</span>
                                        </div>
                                    </li>
                                `;
                            });
                        }
                        itemsHtml += '</ul>';

                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-amber-50/40 transition-colors';
                        tr.innerHTML = `
                            <td class="p-3.5 font-mono font-bold text-[#0B1E3F] whitespace-nowrap">${p.no_pembelian}</td>
                            <td class="p-3.5 text-slate-600 whitespace-nowrap">${tgl}</td>
                            <td class="p-3.5 font-bold text-slate-900 whitespace-nowrap">${p.supplier}</td>
                            <td class="p-3.5">${itemsHtml}</td>
                            <td class="p-3.5 text-right font-black text-[#0B1E3F] whitespace-nowrap">Rp ${totalRp}</td>
                            <td class="p-3.5 text-slate-500 font-medium">${p.keterangan || '-'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    renderOwnerPagination('purchasePagination', result.meta, loadPurchases);
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-400">Belum ada riwayat transaksi pengadaan barang.</td></tr>';
                    renderOwnerPagination('purchasePagination', result.meta, loadPurchases);
                }
            } catch (err) {
                console.error('Gagal memuat riwayat pengadaan', err);
                tbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-rose-600">${err.message || 'Riwayat pengadaan gagal dimuat.'}</td></tr>`;
                document.getElementById('purchasePagination').textContent = 'Periksa koneksi lalu coba muat ulang.';
            }
        }

        // 3. Ambil Katalog Sparepart untuk Form Dropdown
        function renderSparepartsCatalog(parts = sparepartsList) {
            const tbody = document.getElementById('ownerSparepartsRows');
            tbody.replaceChildren();

            if (parts.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 5;
                cell.className = 'p-6 text-center text-slate-400';
                cell.textContent = 'Belum ada data sparepart.';
                row.appendChild(cell);
                tbody.appendChild(row);
                return;
            }

            parts.forEach(part => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-amber-50/40';
                [
                    part.kode_barang,
                    part.nama_barang,
                    part.stok,
                    `Rp ${Number(part.harga_beli || 0).toLocaleString('id-ID')}`,
                    `Rp ${Number(part.harga_jual || 0).toLocaleString('id-ID')}`
                ].forEach((value, index) => {
                    const cell = document.createElement('td');
                    cell.className = `p-3.5 ${index >= 2 ? 'text-right tabular-nums' : ''}`;
                    cell.textContent = value ?? '-';
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            });
        }

        async function loadSparepartsCatalog() {
            try {
                const res = await fetch('/api/spareparts?per_page=100');
                const result = await res.json();
                if (!res.ok || result.status !== 'success') throw new Error(result.message || 'Katalog sparepart gagal dimuat.');
                if (result.status === 'success') {
                    sparepartsList = result.data;
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadOwnerParts(page = 1) {
            ownerPartsPage = page;
            const search = document.getElementById('ownerPartsSearch').value.trim();
            const sort = document.getElementById('ownerPartsSort').value.split(':');
            const params = new URLSearchParams({
                page: String(page),
                per_page: '20',
                sort: sort[0],
                direction: sort[1],
            });
            const stock = document.getElementById('ownerPartsStockFilter').value;
            if (search) params.set('search', search);
            if (stock) params.set('stock', stock);
            try {
                const response = await fetch(`/api/spareparts?${params}`);
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Inventaris gagal dimuat.');
                renderSparepartsCatalog(result.data);
                renderOwnerPagination('ownerPartsPagination', result.meta, loadOwnerParts);
            } catch (error) {
                document.getElementById('ownerSparepartsRows').innerHTML = `<tr><td colspan="5" class="p-6 text-center text-rose-600">${error.message}</td></tr>`;
                document.getElementById('ownerPartsPagination').textContent = 'Periksa koneksi lalu coba muat ulang.';
            }
        }

        function renderReportChart(series) {
            const svg = document.getElementById('reportChart');
            const namespace = 'http://www.w3.org/2000/svg';
            svg.replaceChildren();
            if (!series.length) {
                const empty = document.createElementNS(namespace, 'text');
                empty.setAttribute('x', '400');
                empty.setAttribute('y', '120');
                empty.setAttribute('text-anchor', 'middle');
                empty.setAttribute('fill', '#64748b');
                empty.textContent = 'Tidak ada data servis pada periode ini.';
                svg.append(empty);
                return;
            }
            const values = series.map(row => Number(row.omzet) || 0);
            const maximum = Math.max(...values, 1);
            const left = 42;
            const right = 780;
            const top = 24;
            const bottom = 195;
            const points = values.map((value, index) => {
                const x = left + (series.length === 1 ? 0 : (right - left) * index / (series.length - 1));
                const y = bottom - (bottom - top) * value / maximum;
                return [x, y];
            });
            const baseline = document.createElementNS(namespace, 'line');
            baseline.setAttribute('x1', String(left));
            baseline.setAttribute('x2', String(right));
            baseline.setAttribute('y1', String(bottom));
            baseline.setAttribute('y2', String(bottom));
            baseline.setAttribute('stroke', '#cbd5e1');
            svg.append(baseline);
            const path = document.createElementNS(namespace, 'path');
            path.setAttribute('d', points.map(([x, y], index) => `${index ? 'L' : 'M'} ${x} ${y}`).join(' '));
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', '#0b1e3f');
            path.setAttribute('stroke-width', '3');
            svg.append(path);
            const labelStep = Math.max(1, Math.ceil(series.length / 8));
            points.forEach(([x, y], index) => {
                if (index % labelStep !== 0 && index !== points.length - 1) return;
                const dot = document.createElementNS(namespace, 'circle');
                dot.setAttribute('cx', String(x));
                dot.setAttribute('cy', String(y));
                dot.setAttribute('r', '4');
                dot.setAttribute('fill', '#d4af37');
                const title = document.createElementNS(namespace, 'title');
                title.textContent = `${series[index].label}: Rp ${values[index].toLocaleString('id-ID')}`;
                dot.append(title);
                svg.append(dot);
                const label = document.createElementNS(namespace, 'text');
                label.setAttribute('x', String(x));
                label.setAttribute('y', '220');
                label.setAttribute('text-anchor', 'middle');
                label.setAttribute('font-size', '10');
                label.setAttribute('fill', '#64748b');
                label.textContent = series[index].label;
                svg.append(label);
            });
        }

        function renderReportRows(elementId, rows, columns, emptyMessage) {
            const tbody = document.getElementById(elementId);
            tbody.replaceChildren();
            if (!rows.length) {
                const row = tbody.insertRow();
                const cell = row.insertCell();
                cell.colSpan = columns.length;
                cell.className = 'p-3 text-center text-slate-500';
                cell.textContent = emptyMessage;
                return;
            }
            rows.forEach(values => {
                const row = tbody.insertRow();
                values.forEach((value, index) => {
                    const cell = row.insertCell();
                    cell.className = `border-t border-slate-100 p-2 ${index > 0 ? 'text-right tabular-nums' : ''}`;
                    cell.textContent = value;
                });
            });
        }

        async function loadOwnerReport(page = 1) {
            reportPage = page;
            const message = document.getElementById('reportMessage');
            message.className = 'text-sm text-slate-500';
            message.textContent = 'Memuat laporan…';
            try {
                const response = await fetch(`/api/owner/reports?${reportFilters(reportPage)}`);
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Laporan gagal dimuat.');
                const report = result.data;
                const currency = value => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
                document.getElementById('reportRevenue').textContent = currency(report.summary.omzet);
                document.getElementById('reportCost').textContent = currency(report.summary.modal_sparepart);
                document.getElementById('reportProfit').textContent = currency(report.summary.laba_kotor);
                document.getElementById('reportReceivable').textContent = currency(report.summary.piutang);
                renderReportChart(report.series);
                renderReportRows('reportStatusRows', report.status_counts.map(item => [
                    String(item.status).replaceAll('_', ' '), item.jumlah,
                ]), ['Status', 'Jumlah'], 'Belum ada servis.');
                renderReportRows('reportTechnicianRows', report.technician_counts.map(item => [
                    item.teknisi, item.jumlah_servis, currency(item.omzet),
                ]), ['Teknisi', 'Servis', 'Omzet'], 'Belum ada penugasan.');
                renderReportRows('reportPartsRows', report.top_parts.map(item => [
                    item.nama_barang, item.jumlah_terjual,
                ]), ['Sparepart', 'Terjual'], 'Belum ada sparepart terpakai.');
                renderReportRows('reportReceivableRows', report.receivables.map(item => [
                    item.no_nota,
                    `${item.nama_pelanggan}${item.no_wa ? ` · ${item.no_wa}` : ''}`,
                    item.tanggal || '-',
                    currency(item.total_biaya),
                    currency(item.piutang),
                ]), ['Nota', 'Pelanggan', 'Tanggal', 'Tagihan', 'Piutang'], 'Tidak ada piutang pada periode ini.');
                renderOwnerPagination('reportReceivablePagination', report.receivable_meta, loadOwnerReport);
                message.textContent = `Periode ${report.date_from}–${report.date_to} · ${Number(report.summary.jumlah_servis).toLocaleString('id-ID')} servis`;
            } catch (error) {
                message.className = 'text-sm font-semibold text-rose-700';
                message.textContent = error.message || 'Laporan gagal dimuat. Periksa koneksi dan coba lagi.';
            }
        }

        function openReportExport(path) {
            const params = reportFilters(1);
            params.delete('per_page');
            window.open(`${path}?${params}`, '_blank', 'noopener');
        }

        // Modal Pengadaan Functions
        function openModalPengadaan() {
            const now = new Date();
            const localDate = [
                now.getFullYear(),
                String(now.getMonth() + 1).padStart(2, '0'),
                String(now.getDate()).padStart(2, '0')
            ].join('-');
            document.getElementById('purch_tanggal').value = localDate;
            document.getElementById('purch_supplier').value = '';
            document.getElementById('purch_keterangan').value = '';
            document.getElementById('itemsContainer').innerHTML = '';
            rowCounter = 0;
            addItemRow();
            document.getElementById('modalPengadaan').classList.remove('hidden');
        }

        function closeModalPengadaan() {
            document.getElementById('modalPengadaan').classList.add('hidden');
        }

        function addItemRow() {
            rowCounter++;
            const id = rowCounter;
            const container = document.getElementById('itemsContainer');

            let options = '<option value="">-- Pilih Sparepart yang Ada --</option>';
            sparepartsList.forEach(sp => {
                options += `<option value="${sp.id}" data-hb="${sp.harga_beli}" data-stok="${sp.stok}">
                    ${sp.nama_barang} (Stok: ${sp.stok}) - Beli: Rp ${Number(sp.harga_beli).toLocaleString('id-ID')}
                </option>`;
            });

            const div = document.createElement('div');
            div.id = `itemRow_${id}`;
            div.className = 'p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5 text-xs';
            div.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="font-bold text-[#0B1E3F]">Item #${id}</span>
                    <button type="button" onclick="removeItemRow(${id})" class="text-rose-600 hover:text-rose-800 font-bold text-xs">
                        ✕ Hapus Item
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Pilih Sparepart</label>
                        <select id="item_sp_${id}" required onchange="onSelectItem(${id})"
                            class="w-full py-2 px-2.5 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 font-medium">
                            ${options}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Jumlah (Qty)</label>
                        <input type="number" id="item_qty_${id}" min="1" value="5" required oninput="recalculateTotal()"
                            class="w-full py-2 px-2.5 border border-slate-300 rounded-lg text-xs font-bold focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Beli Satuan (Rp)</label>
                        <input type="number" id="item_hb_${id}" min="0" required oninput="recalculateTotal()"
                            class="w-full py-2 px-2.5 border border-slate-300 rounded-lg text-xs font-bold text-[#0B1E3F] focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>
            `;
            container.appendChild(div);
            recalculateTotal();
        }

        function removeItemRow(id) {
            const row = document.getElementById(`itemRow_${id}`);
            if (row) row.remove();
            recalculateTotal();
        }

        function onSelectItem(id) {
            const select = document.getElementById(`item_sp_${id}`);
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.hb) {
                document.getElementById(`item_hb_${id}`).value = selectedOpt.dataset.hb;
            }
            recalculateTotal();
        }

        function recalculateTotal() {
            let total = 0;
            const container = document.getElementById('itemsContainer');
            const rows = container.children;

            for (let i = 0; i < rows.length; i++) {
                const rowId = rows[i].id.replace('itemRow_', '');
                const qty = parseInt(document.getElementById(`item_qty_${rowId}`)?.value, 10) || 0;
                const hb = parseFloat(document.getElementById(`item_hb_${rowId}`)?.value) || 0;
                total += (qty * hb);
            }

            document.getElementById('purch_total_biaya_display').innerText = `Rp ${total.toLocaleString('id-ID')}`;
        }

        async function submitPengadaan(e) {
            e.preventDefault();
            const supplier = document.getElementById('purch_supplier').value.trim();
            const tanggal = document.getElementById('purch_tanggal').value;
            const keterangan = document.getElementById('purch_keterangan').value.trim();

            const container = document.getElementById('itemsContainer');
            const rows = container.children;

            if (rows.length === 0) {
                return alert('Tambahkan minimal 1 item barang yang dibeli!');
            }

            const items = [];
            for (let i = 0; i < rows.length; i++) {
                const rowId = rows[i].id.replace('itemRow_', '');
                const spId = document.getElementById(`item_sp_${rowId}`)?.value;
                const qty = parseInt(document.getElementById(`item_qty_${rowId}`)?.value, 10);
                const hb = parseFloat(document.getElementById(`item_hb_${rowId}`)?.value);

                if (!spId || isNaN(qty) || qty <= 0 || isNaN(hb) || hb < 0) {
                    return alert('Pastikan semua baris item memiliki sparepart, jumlah valid, dan harga beli yang benar!');
                }

                items.push({
                    sparepart_id: parseInt(spId, 10),
                    jumlah: qty,
                    harga_beli_satuan: hb
                });
            }

            try {
                const res = await fetch('/api/owner/purchases', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        supplier,
                        tanggal_pembelian: tanggal,
                        keterangan,
                        items
                    })
                });

                const data = await res.json();
                if (data.status === 'success') {
                    alert(`✅ ${data.message}\nNo. Pembelian: ${data.data.no_pembelian}\nTotal: Rp ${Number(data.data.total_biaya).toLocaleString('id-ID')}`);
                    closeModalPengadaan();
                    loadAllData();
                } else {
                    alert(`Gagal: ${data.message}`);
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
            }
        }

        // Inisialisasi
        document.getElementById('loadReport').addEventListener('click', () => loadOwnerReport(1));
        document.getElementById('reportPeriod').addEventListener('change', () => loadOwnerReport(1));
        document.getElementById('reportCsv').addEventListener('click', () => openReportExport('/api/owner/reports.csv'));
        document.getElementById('reportPdf').addEventListener('click', () => openReportExport('/api/owner/reports/print'));
        ['purchaseSearch', 'purchaseDateFrom', 'purchaseDateTo', 'purchaseSort'].forEach(id => {
            document.getElementById(id).addEventListener(id === 'purchaseSearch' ? 'input' : 'change', () => loadPurchases(1));
        });
        document.getElementById('ownerPartsSearch').addEventListener('input', () => loadOwnerParts(1));
        ['ownerPartsStockFilter', 'ownerPartsSort'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => loadOwnerParts(1));
        });
        loadAllData();
        window.addEventListener('load', () => {
            if (!window.ServiceRealtime) return;
            window.ServiceRealtime.startStaff('owner', () => {
                loadDashboard();
            });
        });
    </script>
    <script>
        (() => {
            const sidebar = document.getElementById('ownerSidebar');
            const overlay = document.getElementById('ownerSidebarOverlay');
            const toggle = document.getElementById('ownerSidebarToggle');
            const title = document.getElementById('activeSectionTitle');
            const labels = {
                dashboard: 'Dashboard & Laporan',
                staff: 'Manajemen Staf',
                inventory: 'Stok Sparepart'
            };

            function setSidebarOpen(open) {
                sidebar.classList.toggle('-translate-x-full', !open);
                sidebar.classList.toggle('translate-x-0', open);
                overlay.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
            }

            function activateSection(section) {
                document.querySelectorAll('[data-owner-panel]').forEach(panel => {
                    panel.hidden = panel.dataset.ownerPanel !== section;
                });
                document.querySelectorAll('[data-owner-nav]').forEach(button => {
                    const active = button.dataset.ownerNav === section;
                    button.classList.toggle('bg-[#153266]', active);
                    button.classList.toggle('border-amber-400', active);
                    button.classList.toggle('text-amber-300', active);
                    button.classList.toggle('font-semibold', active);
                    button.classList.toggle('border-transparent', !active);
                    button.classList.toggle('text-slate-200', !active);
                    button.classList.toggle('font-medium', !active);
                    button.setAttribute('aria-current', active ? 'page' : 'false');
                });
                title.textContent = labels[section];
                if (window.innerWidth < 768) setSidebarOpen(false);
            }

            document.querySelectorAll('[data-owner-nav]').forEach(button => {
                button.addEventListener('click', () => activateSection(button.dataset.ownerNav));
            });
            toggle.addEventListener('click', () => setSidebarOpen(toggle.getAttribute('aria-expanded') !== 'true'));
            overlay.addEventListener('click', () => setSidebarOpen(false));
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') setSidebarOpen(false);
            });
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) setSidebarOpen(false);
            });
        })();
    </script>
    <script src="/owner-staff.js"></script>
    <script src="/account-menu.js" data-role="owner"></script>
</body>

</html>
