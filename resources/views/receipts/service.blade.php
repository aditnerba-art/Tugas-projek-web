<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $type === 'intake' ? 'Tanda Terima' : 'Nota Pembayaran' }} {{ $service->no_nota }}</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #111; }
        body { margin: 0; background: #f1f5f9; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 16px; }
        .toolbar button, .toolbar select { border: 1px solid #cbd5e1; border-radius: 6px; background: white; padding: 8px 12px; }
        .receipt { box-sizing: border-box; width: 80mm; margin: 0 auto 24px; padding: 5mm; background: white; }
        .receipt h1 { margin: 0 0 3mm; font-size: 15pt; text-align: center; }
        .receipt p { margin: 1.5mm 0; font-size: 9pt; }
        .receipt table { width: 100%; margin-top: 4mm; border-collapse: collapse; font-size: 8.5pt; }
        .receipt th, .receipt td { padding: 1.6mm 0; border-bottom: 1px dashed #888; text-align: left; vertical-align: top; }
        .receipt .amount { text-align: right; white-space: nowrap; }
        .receipt .center { text-align: center; }
        .receipt .qr { display: flex; justify-content: center; margin: 4mm auto 1mm; }
        .receipt .muted { color: #444; font-size: 8pt; }
        .receipt .rule { margin: 3mm 0; border-top: 1px dashed #777; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .receipt { width: 80mm; margin: 0; padding: 3mm; }
        }
    </style>
    <style id="paperStyle">@page { size: 80mm auto; margin: 0; }</style>
</head>
<body>
    <div class="toolbar">
        <label for="paperSize">Lebar kertas</label>
        <select id="paperSize" onchange="setPaper(this.value)">
            <option value="58" @selected($paper === '58')>Thermal 58 mm</option>
            <option value="80" @selected($paper === '80')>Thermal 80 mm</option>
        </select>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="/admin.html">Kembali</a>
    </div>
    <main class="receipt">
        <h1>{{ $type === 'intake' ? 'TANDA TERIMA SERVIS' : 'NOTA PEMBAYARAN' }}</h1>
        <p class="center"><strong>TECKFO SERVICE</strong></p>
        <div class="rule"></div>
        <p>No. Servis: <strong>{{ $service->no_nota }}</strong></p>
        <p>Tanggal: {{ now()->format('d/m/Y H:i') }}</p>
        <p>Pelanggan: {{ $service->customer->nama }}</p>
        <p>WhatsApp: {{ $service->customer->no_wa }}</p>
        <p>Unit: {{ trim(($service->jenis_unit ? $service->jenis_unit.' ' : '').$service->merek_tipe) }}</p>
        @if($service->no_seri)<p>No. Seri: {{ $service->no_seri }}</p>@endif
        <p>Keluhan: {{ $service->keluhan }}</p>
        @if($service->accessories)<p>Kelengkapan: {{ implode(', ', $service->accessories) }}</p>@endif
        @if($service->kelengkapan)<p>Catatan kelengkapan: {{ $service->kelengkapan }}</p>@endif
        @if($service->physical_condition)<p>Kondisi fisik: {{ $service->physical_condition }}</p>@endif

        @if($type === 'payment')
            <table>
                <thead><tr><th>Item</th><th class="amount">Jumlah</th><th class="amount">Subtotal</th></tr></thead>
                <tbody>
                    <tr><td>Jasa servis</td><td class="amount">1</td><td class="amount">Rp {{ number_format((float) $service->biaya_jasa, 0, ',', '.') }}</td></tr>
                    @foreach($service->items as $item)
                        <tr><td>{{ $item->sparepart->nama_barang }}</td><td class="amount">{{ $item->jumlah }}</td><td class="amount">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p><strong>Total: Rp {{ number_format((float) $service->total_biaya, 0, ',', '.') }}</strong></p>
            <p>Pembayaran: {{ strtoupper(str_replace('_', ' ', $service->status_pembayaran)) }}{{ $service->metode_pembayaran ? ' · '.$service->metode_pembayaran : '' }}</p>
            @if($service->warranty_expires_at)<p>Garansi sampai: {{ $service->warranty_expires_at->format('d/m/Y') }}</p>@endif
        @else
            <p>Estimasi biaya: Rp {{ number_format((float) $service->total_biaya, 0, ',', '.') }}</p>
            <p>Estimasi selesai: {{ $service->estimated_completion_at?->format('d/m/Y') ?? 'Belum ditentukan' }}</p>
        @endif

        <div class="rule"></div>
        <p class="center"><strong>Pindai untuk melacak servis</strong></p>
        <div id="trackingQr" class="qr" data-url="{{ $trackingUrl }}" aria-label="QR code tautan pelacakan"></div>
        <p class="center muted">{{ $trackingUrl }}</p>
        <p class="center muted">Simpan tanda terima ini sebagai bukti transaksi.</p>
    </main>
    @vite('resources/js/receipt.js')
    <script>
        function setPaper(width) {
            document.querySelector('.receipt').style.width = `${width}mm`;
            document.getElementById('paperStyle').textContent = `@page { size: ${width}mm auto; margin: 0; } @media print { .receipt { width: ${width}mm; } }`;
        }
        setPaper(document.getElementById('paperSize').value);
    </script>
</body>
</html>
