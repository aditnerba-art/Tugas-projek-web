<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Owner {{ $report['date_from'] }}–{{ $report['date_to'] }}</title>
    <style>
        body { margin: 24px; color: #0b1e3f; font: 13px/1.45 Arial, sans-serif; }
        header, .summary { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; }
        .summary > div { min-width: 150px; padding: 12px; border: 1px solid #dce4ef; border-radius: 8px; }
        h1, h2 { margin: 0 0 8px; }
        h2 { margin-top: 20px; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px; border: 1px solid #dce4ef; text-align: left; }
        th { background: #f1f5f9; }
        .number { text-align: right; white-space: nowrap; }
        .toolbar { margin: 16px 0; }
        @media print { body { margin: 8mm; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Laporan Operasional TECKFO Service</h1>
            <p>Periode pembayaran {{ $report['date_from'] }} sampai {{ $report['date_to'] }} · Refund mengurangi omzet bersih</p>
        </div>
        <button class="toolbar" type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </header>
    <section class="summary">
        <div><strong>Omzet servis</strong><br>Rp {{ number_format($report['summary']['omzet'], 0, ',', '.') }}</div>
        <div><strong>Modal sparepart</strong><br>Rp {{ number_format($report['summary']['modal_sparepart'], 0, ',', '.') }}</div>
        <div><strong>Laba kotor</strong><br>Rp {{ number_format($report['summary']['laba_kotor'], 0, ',', '.') }}</div>
        <div><strong>Jumlah servis</strong><br>{{ number_format($report['summary']['jumlah_servis'], 0, ',', '.') }}</div>
        <div><strong>Estimasi awal tepat waktu</strong><br>
            @if ($report['summary']['akurasi_estimasi_awal']['sampel'] > 0)
                {{ number_format($report['summary']['akurasi_estimasi_awal']['persentase_tepat_waktu'], 1, ',', '.') }}%
                ({{ $report['summary']['akurasi_estimasi_awal']['tepat_waktu'] }}/{{ $report['summary']['akurasi_estimasi_awal']['sampel'] }})
            @else
                Belum ada data
            @endif
        </div>
        <div><strong>Rata-rata selisih estimasi awal</strong><br>
            @if ($report['summary']['akurasi_estimasi_awal']['sampel'] > 0)
                {{ number_format($report['summary']['akurasi_estimasi_awal']['rata_rata_selisih_hari'], 1, ',', '.') }} hari
            @else
                Belum ada data
            @endif
        </div>
    </section>
    <p>Laba kotor mengakui modal sparepart secara proporsional terhadap pembayaran bersih, termasuk pembalikan proporsional saat refund.</p>
    <h2>Ringkasan {{ $report['period'] === 'month' ? 'bulanan' : 'harian' }}</h2>
    <table>
        <thead><tr><th>Periode</th><th class="number">Servis terdaftar</th><th class="number">Omzet bersih</th><th class="number">Modal proporsional</th><th class="number">Laba kotor</th></tr></thead>
        <tbody>
            @forelse ($report['series'] as $row)
                <tr><td>{{ $row['label'] }}</td><td class="number">{{ $row['jumlah_servis'] }}</td><td class="number">Rp {{ number_format($row['omzet'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($row['modal'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($row['laba_kotor'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="5">Tidak ada data servis pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <h2>Servis per status</h2>
    <table><thead><tr><th>Status</th><th class="number">Jumlah servis</th></tr></thead><tbody>
        @foreach ($report['status_counts'] as $row)
            <tr><td>{{ $row->status }}</td><td class="number">{{ $row->jumlah }}</td></tr>
        @endforeach
    </tbody></table>
    <h2>Servis per teknisi</h2>
    <table><thead><tr><th>Teknisi</th><th class="number">Jumlah servis</th><th class="number">Omzet</th></tr></thead><tbody>
        @foreach ($report['technician_counts'] as $row)
            <tr><td>{{ $row->teknisi }}</td><td class="number">{{ $row->jumlah_servis }}</td><td class="number">Rp {{ number_format($row->omzet, 0, ',', '.') }}</td></tr>
        @endforeach
    </tbody></table>
    <h2>Sparepart terlaris</h2>
    <table><thead><tr><th>Sparepart</th><th class="number">Terjual</th><th class="number">Nilai jual</th><th class="number">Modal</th></tr></thead><tbody>
        @foreach ($report['top_parts'] as $part)
            <tr><td>{{ $part['nama_barang'] }}</td><td class="number">{{ $part['jumlah_terjual'] }}</td><td class="number">Rp {{ number_format($part['nilai_jual'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($part['modal'], 0, ',', '.') }}</td></tr>
        @endforeach
    </tbody></table>
    <h2>Rekonsiliasi kas harian per kasir</h2>
    <table><thead><tr><th>Tanggal</th><th>Kasir</th><th class="number">Tunai bersih</th><th class="number">Non-tunai bersih</th><th class="number">Total bersih</th><th class="number">Transaksi</th></tr></thead><tbody>
        @forelse ($report['cash_summary'] as $cash)
            <tr>
                <td>{{ $cash['tanggal'] }}</td>
                <td>{{ $cash['kasir'] }}</td>
                <td class="number">Rp {{ number_format($cash['tunai'], 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format($cash['non_tunai'], 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format($cash['total_bersih'], 0, ',', '.') }}</td>
                <td class="number">{{ $cash['jumlah_transaksi'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Tidak ada transaksi pembayaran pada periode ini.</td></tr>
        @endforelse
    </tbody></table>
    <h2>Piutang / belum lunas</h2>
    <table><thead><tr><th>Nota</th><th>Pelanggan</th><th>Tanggal</th><th>Status bayar</th><th class="number">Tagihan</th><th class="number">Piutang</th></tr></thead><tbody>
        @forelse ($receivables as $service)
            <tr>
                <td>{{ $service->no_nota }}</td>
                <td>{{ $service->customer?->nama ?? '-' }}</td>
                <td>{{ $service->created_at?->format('d-m-Y') }}</td>
                <td>{{ $service->status_pembayaran }}</td>
                <td class="number">Rp {{ number_format((float) $service->total_biaya, 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format((int) $service->piutang, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Tidak ada piutang pada periode ini.</td></tr>
        @endforelse
    </tbody></table>
</body>
</html>
