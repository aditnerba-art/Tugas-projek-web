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
    <title>Master Data Onderdil &amp; Sparepart — TECKFO</title>
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        .gold-border-left { border-left: 4px solid #D4AF37; }
        .gold-border-top  { border-top: 3px solid #D4AF37; }
        .btn-navy {
            background: #0B1E3F;
            color: #fff;
            border: 1px solid #D4AF37;
            transition: background .18s, color .18s;
        }
        .btn-navy:hover { background: #132F63; }
        .btn-gold {
            background: #D4AF37;
            color: #0B1E3F;
            font-weight: 700;
            transition: background .18s;
        }
        .btn-gold:hover { background: #b8952b; }
        .table-head { background: #0B1E3F; color: #fff; border-bottom: 2px solid #D4AF37; }
        .table-row-hover:hover { background: #fef9c3 !important; transition: background .15s; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            ring: 2px;
            border-color: #D4AF37 !important;
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.25);
        }
        .modal-header { background: #0B1E3F; border-bottom: 2px solid #D4AF37; }
        .stat-card { border-top: 3px solid #D4AF37; }
    </style>
</head>

<body data-theme="dashboard" class="bg-slate-50 min-h-screen flex flex-col">

    <!-- Topbar Nav -->
    <div class="app-topbar">
        <span class="text-amber-400 font-bold text-sm mr-1">📦</span>
        <a href="/spareparts.html" class="app-nav-link">Stok Sparepart</a>
    </div>

    <!-- Main Content -->
    <main class="flex-1 p-5 md:p-8">
        <div class="max-w-7xl mx-auto space-y-7">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between sm:items-start gap-4 pb-5 border-b-2 border-amber-400">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-2xl">📦</span>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Master Data Onderdil &amp; Inventaris</h1>
                    </div>
                    <p class="text-sm text-slate-500 ml-9">Kelola sparepart lengkap — Tambah, Ubah, Hapus, Cek Stok &amp; Margin Harga</p>
                </div>
                <!-- Badge Total -->
                <div class="flex items-center ml-9 sm:ml-0">
                    <span id="totalItemsBadge"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold border border-amber-300 text-amber-800 bg-amber-50 shadow-sm whitespace-nowrap">
                        <span>🗂️</span> Memuat data...
                    </span>
                </div>
            </div>

            <!-- Summary Stats Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 stat-card p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-blue-50 text-blue-700 text-xl">🧮</div>
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Total Jenis Barang</p>
                        <p class="text-xl font-extrabold text-slate-800" id="statTotal">—</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 stat-card p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-rose-50 text-rose-600 text-xl">⚠️</div>
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Stok Menipis / Habis</p>
                        <p class="text-xl font-extrabold text-slate-800" id="statLow">—</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 stat-card p-4 flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-emerald-50 text-emerald-700 text-xl">✅</div>
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Stok Aman</p>
                        <p class="text-xl font-extrabold text-slate-800" id="statOk">—</p>
                    </div>
                </div>
            </div>

            <!-- Add New Sparepart Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Card Header -->
                <div class="modal-header px-6 py-4 flex items-center gap-2.5">
                    <span class="text-amber-400 text-lg">➕</span>
                    <h2 class="text-base font-bold text-white">Tambah Sparepart Baru</h2>
                </div>
                <!-- Form -->
                <div class="p-6">
                    <form id="formSparepart" onsubmit="simpanSparepart(event)" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kode Barang <span class="text-red-500">*</span></label>
                                <input type="text" id="kode_barang" placeholder="Ex: RAM-D4-8GB" required
                                    class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-mono font-bold bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Barang / Onderdil <span class="text-red-500">*</span></label>
                                <input type="text" id="nama_barang" placeholder="Nama lengkap sparepart" required
                                    class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Stok Awal</label>
                                <input type="number" id="stok" placeholder="0" min="0" value="0"
                                    class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-semibold bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Harga Beli (Rp)</label>
                                <input type="number" id="harga_beli" placeholder="0" min="0" required
                                    class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Harga Jual ke Pelanggan (Rp) <span class="text-red-500">*</span></label>
                                <input type="number" id="harga_jual" placeholder="0" min="0" required
                                    class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-bold text-emerald-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="btn-gold w-full py-2.5 px-4 rounded-lg text-sm shadow-sm flex items-center justify-center gap-1.5">
                                    <span>➕</span> Simpan Sparepart
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Card Header -->
                <div class="modal-header px-6 py-4 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-lg">📋</span>
                        <h2 class="text-base font-bold text-white">Daftar Stok &amp; Master Onderdil</h2>
                    </div>
                    <!-- Search & Reload -->
                    <div class="flex gap-2 max-w-xs w-full">
                        <input type="text" id="searchInput" oninput="filterSpareparts()" placeholder="Cari nama atau kode barang..."
                            class="flex-1 py-2 px-3 border border-slate-600 rounded-lg text-xs bg-slate-800 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                        <button onclick="loadSpareparts()" class="px-3 py-2 bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-sm rounded-lg border border-amber-400 transition" title="Muat Ulang">
                            🔄
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="table-head">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px]">Kode Barang</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px]">Nama Onderdil</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px] text-center">Stok Fisik</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px] text-right">Harga Beli</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px] text-right">Harga Jual</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px] text-right">Est. Profit</th>
                                <th class="px-4 py-3 font-semibold text-amber-300 uppercase tracking-wider text-[11px] text-center">Aksi (CRUD)</th>
                            </tr>
                        </thead>
                        <tbody id="sparepartTable" class="divide-y divide-slate-100">
                            <!-- Diisi via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Edit Sparepart -->
    <div id="modalEditSparepart" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
            <!-- Modal Header -->
            <div class="modal-header px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <span class="text-amber-400 text-base">✏️</span>
                    <h3 class="text-base font-bold text-white">Edit Data Onderdil</h3>
                </div>
                <button type="button" onclick="closeModalEdit()" class="text-slate-400 hover:text-amber-400 text-2xl font-bold leading-none transition">&times;</button>
            </div>

            <form onsubmit="submitEditSparepart(event)" class="p-6 space-y-4">
                <input type="hidden" id="edit_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kode Barang <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_kode_barang" required
                        class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-mono font-bold bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_nama_barang" required
                        class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Stok Fisik</label>
                        <input type="number" id="edit_stok" min="0" required
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-semibold bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Harga Beli</label>
                        <input type="number" id="edit_harga_beli" min="0" required
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Harga Jual</label>
                        <input type="number" id="edit_harga_jual" min="0" required
                            class="w-full py-2.5 px-3.5 border border-slate-300 rounded-lg text-xs font-bold text-emerald-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 transition">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModalEdit()"
                        class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 py-2.5 btn-navy rounded-lg text-xs font-bold shadow-sm">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let sparepartsCache = [];

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            menu.classList.toggle('hidden');
        }

        // Ambil Data Semua Sparepart
        async function loadSpareparts() {
            try {
                const res = await fetch('/api/spareparts');
                const result = await res.json();
                if (result.status === 'success') {
                    sparepartsCache = result.data;
                    const total = sparepartsCache.length;
                    const lowStock = sparepartsCache.filter(i => parseInt(i.stok, 10) <= 5).length;
                    const okStock = total - lowStock;

                    document.getElementById('totalItemsBadge').innerHTML = `<span>🗂️</span> Total: ${total} Jenis Sparepart`;
                    document.getElementById('statTotal').textContent = total;
                    document.getElementById('statLow').textContent = lowStock;
                    document.getElementById('statOk').textContent = okStock;
                    renderSparepartsTable(sparepartsCache);
                }
            } catch (err) {
                console.error(err);
            }
        }

        function filterSpareparts() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            if (!query) {
                return renderSparepartsTable(sparepartsCache);
            }
            const filtered = sparepartsCache.filter(item => 
                (item.kode_barang && item.kode_barang.toLowerCase().includes(query)) ||
                (item.nama_barang && item.nama_barang.toLowerCase().includes(query))
            );
            renderSparepartsTable(filtered);
        }

        function renderSparepartsTable(items) {
            const tbody = document.getElementById('sparepartTable');
            tbody.innerHTML = '';

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 text-sm">Tidak ada onderdil yang cocok dengan pencarian.</td></tr>';
                return;
            }

            items.forEach(item => {
                const stokNum = parseInt(item.stok, 10);
                const hb = parseFloat(item.harga_beli || 0);
                const hj = parseFloat(item.harga_jual || 0);
                const profit = hj - hb;

                // Status Badge Stok
                let badgeStok = '';
                if (stokNum === 0) {
                    badgeStok = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">Habis (0)</span>';
                } else if (stokNum <= 5) {
                    badgeStok = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Menipis (${stokNum})</span>`;
                } else {
                    badgeStok = `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Aman (${stokNum})</span>`;
                }

                const tr = document.createElement('tr');
                tr.className = 'table-row-hover bg-white';
                tr.innerHTML = `
                    <td class="px-4 py-3 font-mono font-bold text-blue-700 whitespace-nowrap">${item.kode_barang}</td>
                    <td class="px-4 py-3 font-semibold text-slate-800">${item.nama_barang}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">${badgeStok}</td>
                    <td class="px-4 py-3 text-right text-slate-500 whitespace-nowrap">Rp ${hb.toLocaleString('id-ID')}</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 whitespace-nowrap">Rp ${hj.toLocaleString('id-ID')}</td>
                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 whitespace-nowrap">+Rp ${profit.toLocaleString('id-ID')}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick="openModalEdit(${item.id})"
                                class="px-2.5 py-1 btn-navy rounded text-xs font-semibold transition" title="Edit">
                                ✏️ Edit
                            </button>
                            <button onclick="hapusSparepart(${item.id}, '${encodeURIComponent(item.nama_barang)}')"
                                class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded text-xs transition" title="Hapus">
                                🗑️ Hapus
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Tambah Sparepart Baru (Create)
        async function simpanSparepart(e) {
            e.preventDefault();
            const payload = {
                kode_barang: document.getElementById('kode_barang').value.trim(),
                nama_barang: document.getElementById('nama_barang').value.trim(),
                stok: parseInt(document.getElementById('stok').value, 10) || 0,
                harga_beli: parseFloat(document.getElementById('harga_beli').value) || 0,
                harga_jual: parseFloat(document.getElementById('harga_jual').value) || 0
            };

            try {
                const res = await fetch('/api/spareparts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert('✅ Sparepart berhasil ditambahkan!');
                    document.getElementById('formSparepart').reset();
                    loadSpareparts();
                } else {
                    alert(`Gagal: ${data.message}`);
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi.');
            }
        }

        // Buka Modal Edit
        function openModalEdit(id) {
            const item = sparepartsCache.find(x => x.id === id);
            if (!item) return;

            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_kode_barang').value = item.kode_barang;
            document.getElementById('edit_nama_barang').value = item.nama_barang;
            document.getElementById('edit_stok').value = item.stok;
            document.getElementById('edit_harga_beli').value = item.harga_beli;
            document.getElementById('edit_harga_jual').value = item.harga_jual;

            document.getElementById('modalEditSparepart').classList.remove('hidden');
        }

        function closeModalEdit() {
            document.getElementById('modalEditSparepart').classList.add('hidden');
        }

        // Simpan Edit (Update)
        async function submitEditSparepart(e) {
            e.preventDefault();
            const id = document.getElementById('edit_id').value;
            const payload = {
                kode_barang: document.getElementById('edit_kode_barang').value.trim(),
                nama_barang: document.getElementById('edit_nama_barang').value.trim(),
                stok: parseInt(document.getElementById('edit_stok').value, 10) || 0,
                harga_beli: parseFloat(document.getElementById('edit_harga_beli').value) || 0,
                harga_jual: parseFloat(document.getElementById('edit_harga_jual').value) || 0
            };

            try {
                const res = await fetch(`/api/spareparts/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert('✅ Data sparepart berhasil diperbarui!');
                    closeModalEdit();
                    loadSpareparts();
                } else {
                    alert(`Gagal: ${data.message}`);
                }
            } catch (err) {
                alert('Terjadi kesalahan');
            }
        }

        // Hapus Sparepart (Delete)
        async function hapusSparepart(id, encNama) {
            const nama = decodeURIComponent(encNama);
            if (!confirm(`Yakin ingin menghapus onderdil "${nama}"? Data yang sudah dihapus tidak dapat dikembalikan.`)) {
                return;
            }

            try {
                const res = await fetch(`/api/spareparts/${id}`, { method: 'DELETE' });
                const data = await res.json();
                if (data.status === 'success') {
                    alert(`✅ ${data.message}`);
                    loadSpareparts();
                } else {
                    alert(`⚠️ Gagal: ${data.message}`);
                }
            } catch (err) {
                alert('Terjadi kesalahan koneksi.');
            }
        }

        // Inisialisasi
        loadSpareparts();
    </script>
</body>

</html>
