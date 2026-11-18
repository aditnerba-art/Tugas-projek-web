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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>

<body data-theme="dashboard" class="bg-slate-50 min-h-screen flex flex-col font-sans text-slate-900">
    <div class="app-topbar">
        <a href="owner.html" class="app-nav-link">👑 Panel Owner</a>
        <a href="/spareparts.html" class="app-nav-link">📦 Kelola Sparepart</a>
    </div>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 border-l-4 border-amber-400">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#0B1E3F] text-amber-400 mb-2 border border-amber-400/40">
                        👑 Executive Dashboard & Oversight
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

            <!-- Pengaturan akun staf, hanya tersedia untuk owner -->
            <section class="bg-white p-7 sm:p-8 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-amber-400 space-y-5">
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
                        <span>Estimasi Arus Kas Bersih (Net)</span>
                        <span class="p-2 bg-amber-50 text-amber-800 rounded-xl text-base">📈</span>
                    </div>
                    <div id="labaBersih" class="text-3xl font-black text-emerald-600">Rp 0</div>
                    <div class="text-xs text-slate-500">Omzet servis dikurangi biaya pengadaan</div>
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
            </div>
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

    <script>
        let sparepartsList = [];
        let rowCounter = 0;

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        async function loadAllData() {
            await Promise.all([loadDashboard(), loadPurchases(), loadSparepartsCatalog()]);
        }

        // 1. Ambil Data Dashboard Owner
        async function loadDashboard() {
            try {
                const res = await fetch('/api/owner/dashboard');
                const result = await res.json();

                if (result.status === 'success') {
                    const { summary, statusCounts, purchases, spareparts } = result.data;

                    document.getElementById('totalUnit').innerText = summary.total_unit || 0;
                    document.getElementById('unitSelesai').innerText = summary.unit_selesai || 0;

                    const nominalPendapatan = summary.total_pendapatan ? parseFloat(summary.total_pendapatan) : 0;
                    document.getElementById('totalPendapatan').innerText = `Rp ${nominalPendapatan.toLocaleString('id-ID')}`;

                    const nominalPengadaan = purchases.total_pengeluaran_pengadaan ? parseFloat(purchases.total_pengeluaran_pengadaan) : 0;
                    document.getElementById('totalPengadaan').innerText = `Rp ${nominalPengadaan.toLocaleString('id-ID')}`;
                    document.getElementById('totalTransaksiPengadaan').innerText = `${purchases.total_transaksi_pengadaan || 0} transaksi pengadaan`;

                    // Hitung Laba Bersih Arus Kas (Pendapatan - Pengadaan)
                    const netCash = nominalPendapatan - nominalPengadaan;
                    const elLaba = document.getElementById('labaBersih');
                    if (netCash >= 0) {
                        elLaba.className = 'text-3xl font-black text-emerald-600';
                        elLaba.innerText = `+Rp ${netCash.toLocaleString('id-ID')}`;
                    } else {
                        elLaba.className = 'text-3xl font-black text-rose-600';
                        elLaba.innerText = `-Rp ${Math.abs(netCash).toLocaleString('id-ID')}`;
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
            }
        }

        // 2. Ambil Riwayat Pengadaan
        async function loadPurchases() {
            try {
                const res = await fetch('/api/owner/purchases');
                const result = await res.json();
                const tbody = document.getElementById('purchasesTable');
                tbody.innerHTML = '';

                if (result.status === 'success' && result.data.length > 0) {
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
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-400">Belum ada riwayat transaksi pengadaan barang.</td></tr>';
                }
            } catch (err) {
                console.error('Gagal memuat riwayat pengadaan', err);
            }
        }

        // 3. Ambil Katalog Sparepart untuk Form Dropdown
        async function loadSparepartsCatalog() {
            try {
                const res = await fetch('/api/spareparts');
                const result = await res.json();
                if (result.status === 'success') {
                    sparepartsList = result.data;
                }
            } catch (err) {
                console.error(err);
            }
        }

        // Modal Pengadaan Functions
        function openModalPengadaan() {
            document.getElementById('purch_tanggal').value = new Date().toISOString().slice(0, 10);
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
        loadAllData();
    </script>
    <script src="/owner-staff.js"></script>
    <script src="/account-menu.js" data-role="owner"></script>
</body>

</html>
