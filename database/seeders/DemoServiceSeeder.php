<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceStatusLog;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoServiceSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'masuk', 'diperiksa', 'menunggu_persetujuan', 'dikerjakan',
            'menunggu_sparepart', 'selesai', 'diambil', 'batal',
        ];
        $technicianIds = User::where('role', 'teknisi')->orderBy('id')->pluck('id');
        $part = Sparepart::firstOrCreate(
            ['kode_barang' => 'DEMO-PART-001'],
            ['nama_barang' => 'Sparepart Demo', 'stok' => 12, 'harga_beli' => 100000, 'harga_jual' => 150000],
        );

        foreach ($statuses as $index => $status) {
            $customer = Customer::firstOrCreate(
                ['no_wa' => '62812000000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                ['nama' => 'Pelanggan Demo '.($index + 1), 'alamat' => 'Alamat untuk pengujian manual'],
            );
            $nota = 'DEMO-SRV-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            if (Service::where('no_nota', $nota)->exists()) {
                continue;
            }

            $service = Service::factory()->create([
                'no_nota' => $nota,
                'customer_id' => $customer->id,
                'teknisi_id' => $technicianIds->isNotEmpty() ? $technicianIds[$index % $technicianIds->count()] : null,
                'merek_tipe' => 'Unit Demo '.($index + 1),
                'keluhan' => 'Data demonstrasi untuk status '.str_replace('_', ' ', $status),
                'status' => $status,
                'total_biaya' => $index >= 3 && $index <= 6 ? 150000 : 0,
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays(max(0, $index - 1)),
            ]);

            if ($index === 5 || $index === 6) {
                $service->payments()->create([
                    'amount' => $index === 5 ? 50000 : (int) $service->total_biaya,
                    'method' => 'tunai',
                    'paid_at' => $service->updated_at,
                    'note' => 'Pembayaran data demo',
                    'type' => 'payment',
                ]);
                $service->syncPaymentSummary();
            }

            ServiceStatusLog::create([
                'service_id' => $service->id,
                'status_lama' => null,
                'status_baru' => $status,
                'catatan' => 'Status data demo.',
                'user_id' => null,
                'created_at' => $service->created_at,
            ]);

            if ($index >= 3 && $index <= 6) {
                ServiceItem::create([
                    'service_id' => $service->id,
                    'sparepart_id' => $part->id,
                    'jumlah' => 1,
                    'harga_modal' => $part->harga_beli,
                    'harga_satuan' => $part->harga_jual,
                    'subtotal' => $part->harga_jual,
                ]);
            }
        }
    }
}
