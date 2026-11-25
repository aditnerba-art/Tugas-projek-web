<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceStatusLog;
use App\Models\Sparepart;
use Illuminate\Database\Seeder;

class DemoYearServiceSeeder extends Seeder
{
    public function run(): void
    {
        $part = Sparepart::firstOrCreate(
            ['kode_barang' => 'DEMO-YEAR-PART-001'],
            ['nama_barang' => 'Sparepart Riwayat Demo', 'stok' => 24, 'harga_beli' => 100000, 'harga_jual' => 150000],
        );
        $statuses = ['selesai', 'diambil', 'dikerjakan', 'menunggu_sparepart', 'batal', 'selesai', 'diperiksa'];

        for ($week = 1; $week <= 52; $week++) {
            $nota = 'DEMO-YEAR-'.str_pad((string) $week, 3, '0', STR_PAD_LEFT);
            if (Service::where('no_nota', $nota)->exists()) {
                continue;
            }

            $createdAt = now(config('app.timezone'))->subDays($week * 7)->setTime(10, 0);
            $customer = Customer::firstOrCreate(
                ['no_wa' => '6281399'.str_pad((string) $week, 7, '0', STR_PAD_LEFT)],
                ['nama' => 'Pelanggan Riwayat Demo '.$week, 'alamat' => 'Data simulasi khusus demo'],
            );
            $status = $statuses[($week - 1) % count($statuses)];
            $completed = in_array($status, ['selesai', 'diambil'], true);
            $durationDays = 2 + ($week % 5);
            $actualCompletedAt = $createdAt->copy()->addDays($durationDays);
            $estimatedDays = $durationDays + ($week % 4 === 0 ? -1 : 1);
            $total = 250000 + ($week * 1000);
            $service = Service::factory()->create([
                'no_nota' => $nota,
                'customer_id' => $customer->id,
                'merek_tipe' => 'Unit Riwayat Demo '.$week,
                'keluhan' => 'Data simulasi servis minggu ke-'.$week,
                'status' => $status,
                'biaya_jasa' => $total - 150000,
                'total_biaya' => $total,
                'estimated_completion_at' => $createdAt->copy()->addDays($estimatedDays)->toDateString(),
                'initial_estimated_completion_at' => $createdAt->copy()->addDays($estimatedDays)->toDateString(),
                'actual_completed_at' => $completed ? $actualCompletedAt : null,
                'created_at' => $createdAt,
                'updated_at' => $completed ? $actualCompletedAt : $createdAt->copy()->addDay(),
            ]);

            ServiceStatusLog::create([
                'service_id' => $service->id,
                'status_lama' => null,
                'status_baru' => $status,
                'catatan' => 'Status data simulasi riwayat demo.',
                'user_id' => null,
                'created_at' => $createdAt,
            ]);

            if ($week % 2 === 0) {
                ServiceItem::create([
                    'service_id' => $service->id,
                    'sparepart_id' => $part->id,
                    'jumlah' => 1,
                    'harga_modal' => $part->harga_beli,
                    'harga_satuan' => $part->harga_jual,
                    'subtotal' => $part->harga_jual,
                ]);
            }

            if ($completed && $week % 3 !== 2) {
                $service->payments()->create([
                    'amount' => $week % 3 === 0 ? $total : (int) floor($total / 2),
                    'method' => $week % 2 === 0 ? 'tunai' : 'transfer',
                    'paid_at' => $actualCompletedAt,
                    'note' => 'Pembayaran data riwayat demo',
                    'type' => 'payment',
                ]);
                $service->syncPaymentSummary();
            }
        }
    }
}
