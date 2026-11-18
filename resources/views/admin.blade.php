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

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Kelengkapan Unit Bawaan</label>
                        <input type="text" id="kelengkapan" placeholder="Contoh: Unit, Charger Original, Tas Laptop"
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
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
                    <span>Penghapusan pelanggan akan menghapus permanen seluruh riwayat servis, detail sparepart, log teknisi, dan kode akses terkait. Jumlah servis serta pendapatan historis ikut berubah dan data tidak dapat dipulihkan.</span>
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
        async function loadActiveServices() {
            try {
                await loadAccessCodes();
                const res = await fetch('/api/admin/services-active');
                const result = await res.json();
                const tbody = document.getElementById('activeServicesTable');
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
                            </td>
                            <td class="p-3.5 whitespace-nowrap font-black text-slate-900">Rp ${totalBiayaRp}</td>
                            <td class="p-3.5 whitespace-nowrap">${badgeBayar}</td>
                            <td class="p-3.5 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="openModalPelunasan(${srv.id}, '${srv.no_nota}', '${encodeURIComponent(srv.nama_pelanggan)}', '${encodeURIComponent(srv.merek_tipe)}', ${srv.total_biaya || 0}, '${srv.status_pembayaran}')"
                                        class="inline-flex items-center gap-1.5 bg-[#0B1E3F] hover:bg-[#132F63] text-white px-3.5 py-2 rounded-xl text-xs font-bold border border-amber-400/80 shadow-sm transition">
                                        💵 Pelunasan
                                    </button>
                                    <button type="button" class="delete-service-button inline-flex items-center gap-1 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </td>
                        `;
                        tr.querySelector('.delete-service-button').addEventListener('click', () => hapusServis(srv.id, srv.no_nota));
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
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 text-sm">Tidak ada unit servis aktif saat ini.</td></tr>';
                }
            } catch (err) {
                console.error('Gagal memuat servis aktif', err);
                document.getElementById('activeServicesTable').innerHTML =
                    '<tr><td colspan="7" class="p-8 text-center text-rose-600 text-sm">Daftar servis atau status kode akses gagal dimuat.</td></tr>';
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

        async function simpanServis() {
            const payload = {
                nama: document.getElementById('nama').value,
                no_wa: document.getElementById('no_wa').value,
                jenis_unit: document.getElementById('jenis_unit').value,
                merek_tipe: document.getElementById('merek_tipe').value,
                no_seri: document.getElementById('no_seri').value,
                kelengkapan: document.getElementById('kelengkapan').value,
                keluhan: document.getElementById('keluhan').value
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
                if (data.service_id && data.customer_token) {
                    sessionCustomerAccessCodes.set(String(data.service_id), data.customer_token);
                }
                document.getElementById('formPendaftaran').reset();
                await Promise.all([loadActiveServices(), loadCustomers()]);
                showServiceSuccessModal(data, payload);
            } else {
                alert(`Gagal: ${data.message}`);
            }
        }

        async function loadCustomers() {
            const tbody = document.getElementById('customersTable');
            const message = document.getElementById('customersMessage');
            tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-400">Memuat data pelanggan…</td></tr>';
            message.textContent = '';

            try {
                const response = await fetch('/api/admin/customers');
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
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-rose-600 font-semibold">Data pelanggan gagal dimuat.</td></tr>';
                message.textContent = error.message || 'Terjadi kesalahan saat memuat pelanggan.';
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
            const konfirmasi = confirm('Yakin ingin menghapus permanen data pelanggan beserta seluruh riwayat servisnya?');
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

        // Inisialisasi data saat halaman dibuka
        loadActiveServices();
        loadCustomers();
    </script>
    <script src="/account-menu.js" data-role="admin"></script>
</body>

</html>
