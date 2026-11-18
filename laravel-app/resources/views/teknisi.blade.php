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
    <title>Panel Teknisi - TECKFO Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/theme.css">
</head>

<body data-theme="dashboard" class="bg-slate-50 text-slate-900 min-h-screen flex flex-col font-sans">
    <div class="app-topbar">
        <a href="teknisi.html" class="app-nav-link">🛠️ Panel Teknisi</a>
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
                    <p class="text-xs text-slate-500 mt-1">Diagnosa kerusakan, pasang onderdil resmi, input biaya jasa, dan perbarui log status servis</p>
                </div>
                <button onclick="initData()"
                    class="inline-flex items-center gap-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold px-4 py-2.5 rounded-xl border border-slate-300 transition shadow-sm">
                    🔄 Refresh Data
                </button>
            </div>

            <!-- Form Update Status -->
            <div class="bg-white p-7 sm:p-8 rounded-2xl border border-slate-200 border-t-4 border-amber-400 shadow-sm space-y-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Pilih Unit Servis yang Dikerjakan <span class="text-rose-500">*</span></label>
                    <select id="service_id" required onchange="onSelectService()"
                        class="w-full py-3 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 shadow-sm">
                        <option value="">-- Pilih Unit Servis --</option>
                    </select>
                </div>

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
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Biaya Jasa Servis (Rp)</label>
                            <input type="number" id="biaya_jasa" placeholder="0" min="0" oninput="calculateEstimatedTotal()"
                                class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 font-bold">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-[#0B1E3F] mb-1.5">Estimasi Total Biaya (Rp)</label>
                            <input type="number" id="total_biaya" placeholder="0" min="0"
                                class="w-full py-2.5 px-3.5 bg-slate-50 border-2 border-amber-400/80 rounded-xl text-sm text-emerald-700 font-black focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                            <span class="text-[11px] text-slate-500 font-medium">Jasa + total onderdil terpasang</span>
                        </div>
                    </div>

                    <!-- Input Pemasangan Sparepart Baru -->
                    <div class="p-5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-[#0B1E3F]">Pasang Onderdil / Sparepart Baru (Otomatis Potong Stok)</label>
                            <span id="sparepart_info" class="text-xs text-amber-800 font-bold"></span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Pilih Sparepart</label>
                                <select id="sparepart_id" onchange="onSparepartChange()"
                                    class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500">
                                    <option value="">-- Tidak Memasang Sparepart Baru --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah (Qty)</label>
                                <input type="number" id="jumlah_sparepart" min="1" placeholder="1" oninput="calculateEstimatedTotal()"
                                    class="w-full py-2.5 px-3.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-500 font-bold">
                            </div>
                        </div>
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
            </div>
        </div>
    </main>

    <script>
        let servicesCache = [];
        let sparepartsCache = [];

        function toggleNavbarMenu() {
            const menu = document.getElementById('navMobileMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        // Ambil daftar unit servis yang belum diambil pelanggan
        async function loadPendingServices() {
            try {
                const res = await fetch('/api/admin/services-active');
                const result = await res.json();

                if (result.status === 'success') {
                    servicesCache = result.data;
                    const select = document.getElementById('service_id');
                    select.innerHTML = '<option value="">-- Pilih Unit Servis --</option>';

                    result.data.forEach(srv => {
                        const opt = document.createElement('option');
                        opt.value = srv.id;
                        const tekName = srv.nama_teknisi ? `[PIC: ${srv.nama_teknisi}]` : '[Belum Ditugaskan]';
                        opt.innerText = `[${srv.no_nota}] ${srv.nama_pelanggan} - ${srv.merek_tipe} ${tekName} (${(srv.status || '').toUpperCase()})`;
                        select.appendChild(opt);
                    });
                }
            } catch (err) {
                console.error('Gagal memuat unit servis', err);
            }
        }

        // Ambil daftar sparepart
        async function loadSpareparts() {
            try {
                const res = await fetch('/api/spareparts');
                const result = await res.json();

                if (result.status === 'success') {
                    sparepartsCache = result.data;
                    const select = document.getElementById('sparepart_id');
                    select.innerHTML = '<option value="">-- Tidak Memasang Sparepart Baru --</option>';

                    result.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.dataset.stok = item.stok;
                        opt.dataset.harga = item.harga_jual;
                        opt.innerText = `${item.nama_barang} (Stok: ${item.stok}) - Rp ${Number(item.harga_jual).toLocaleString('id-ID')}`;
                        select.appendChild(opt);
                    });
                }
            } catch (err) {
                console.error('Gagal memuat daftar sparepart', err);
            }
        }

        // Saat unit servis dipilih dari dropdown
        async function onSelectService() {
            const srvId = document.getElementById('service_id').value;
            const banner = document.getElementById('unitInfoBanner');

            if (!srvId) {
                banner.classList.add('hidden');
                document.getElementById('biaya_jasa').value = '';
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
                document.getElementById('biaya_jasa').value = srv.biaya_jasa || 0;
                document.getElementById('total_biaya').value = srv.total_biaya || 0;
                document.getElementById('status_baru').value = srv.status || 'diperiksa';
                banner.classList.remove('hidden');

                // Ambil info onderdil yang sudah dipasang lewat endpoint nota
                try {
                    const resNota = await fetch(`/api/admin/nota/${srv.no_nota}`);
                    const notaRes = await resNota.json();
                    const listWrap = document.getElementById('banner_onderdil_list');
                    listWrap.innerHTML = '';

                    if (notaRes.status === 'success' && notaRes.data.onderdil && notaRes.data.onderdil.length > 0) {
                        notaRes.data.onderdil.forEach(item => {
                            listWrap.innerHTML += `
                                <div class="flex justify-between items-center bg-white border border-slate-200 px-3 py-1.5 rounded-lg text-xs">
                                    <span class="font-semibold text-slate-800">• ${item.nama_barang} (x${item.jumlah})</span>
                                    <span class="font-bold text-emerald-700">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</span>
                                </div>
                            `;
                        });
                    } else {
                        listWrap.innerHTML = '<span class="text-slate-500 italic">Belum ada onderdil terpasang.</span>';
                    }
                } catch (e) {
                    console.error(e);
                }
            }
        }

        // Tampilkan info stok ketika sparepart dipilih
        function onSparepartChange() {
            const select = document.getElementById('sparepart_id');
            const info = document.getElementById('sparepart_info');
            const qtyInput = document.getElementById('jumlah_sparepart');
            const selectedOpt = select.options[select.selectedIndex];

            if (select.value && selectedOpt) {
                const stok = selectedOpt.dataset.stok;
                const harga = Number(selectedOpt.dataset.harga).toLocaleString('id-ID');
                info.innerText = `Stok: ${stok} unit | Harga Jual: Rp ${harga}`;
                if (!qtyInput.value || parseInt(qtyInput.value, 10) < 1) {
                    qtyInput.value = 1;
                }
            } else {
                info.innerText = '';
                qtyInput.value = '';
            }
            calculateEstimatedTotal();
        }

        function calculateEstimatedTotal() {
            const srvId = document.getElementById('service_id').value;
            const currentSrv = servicesCache.find(s => s.id == srvId);
            const baseTotalBiaya = currentSrv ? parseFloat(currentSrv.total_biaya || 0) : 0;
            const currentJasa = currentSrv ? parseFloat(currentSrv.biaya_jasa || 0) : 0;

            const inputJasa = parseFloat(document.getElementById('biaya_jasa').value) || 0;
            
            const select = document.getElementById('sparepart_id');
            let sparepartSubtotal = 0;
            if (select.value) {
                const selectedOpt = select.options[select.selectedIndex];
                const hargaSatuan = parseFloat(selectedOpt.dataset.harga) || 0;
                const qty = parseInt(document.getElementById('jumlah_sparepart').value, 10) || 1;
                sparepartSubtotal = hargaSatuan * qty;
            }

            // Estimasi total: Jika ada sparepart baru ditambah jasa
            const previousSparepartTotal = Math.max(0, baseTotalBiaya - currentJasa);
            const newTotal = inputJasa + previousSparepartTotal + sparepartSubtotal;
            document.getElementById('total_biaya').value = newTotal;
        }

        function initData() {
            loadPendingServices();
            loadSpareparts();
        }
        initData();

        async function submitUpdate() {
            const serviceId = document.getElementById('service_id').value;
            const statusBaru = document.getElementById('status_baru').value;
            const biayaJasa = document.getElementById('biaya_jasa').value;
            const totalBiaya = document.getElementById('total_biaya').value;
            const catatanTeknis = document.getElementById('catatan_teknis').value;
            const sparepartId = document.getElementById('sparepart_id').value;
            const jumlahSparepart = document.getElementById('jumlah_sparepart').value;

            if (!serviceId || !catatanTeknis) {
                return alert('Pilih unit servis dan isi catatan diagnosa / pengerjaan!');
            }

            if (sparepartId) {
                const qty = parseInt(jumlahSparepart, 10);
                if (isNaN(qty) || qty <= 0) {
                    return alert('Masukkan jumlah sparepart yang valid (minimal 1)!');
                }
                const select = document.getElementById('sparepart_id');
                const selectedOpt = select.options[select.selectedIndex];
                const stokTersedia = parseInt(selectedOpt.dataset.stok, 10);
                if (qty > stokTersedia) {
                    return alert(`Jumlah melebihi stok yang tersedia (${stokTersedia} unit)!`);
                }
            }

            const payload = {
                service_id: serviceId,
                status_baru: statusBaru,
                biaya_jasa: biayaJasa ? parseFloat(biayaJasa) : 0,
                total_biaya: totalBiaya ? parseFloat(totalBiaya) : 0,
                catatan_teknis: catatanTeknis,
                sparepart_id: sparepartId ? parseInt(sparepartId, 10) : null,
                jumlah_sparepart: sparepartId ? parseInt(jumlahSparepart, 10) : null
            };

            try {
                const res = await fetch('/api/admin/service/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.status === 'success') {
                    alert('✅ Progres pengerjaan dan onderdil berhasil diperbarui!');
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
    <script src="/account-menu.js" data-role="teknisi"></script>
</body>

</html>
