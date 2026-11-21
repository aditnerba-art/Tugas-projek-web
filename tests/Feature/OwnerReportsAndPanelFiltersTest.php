<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerReportsAndPanelFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_service_table_can_filter_initial_status_customer_and_technician(): void
    {
        $admin = User::create([
            'nama' => 'Admin Tes',
            'username' => 'admin_filters',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $technician = User::create([
            'nama' => 'Teknisi Tes',
            'username' => 'technician_filters',
            'password' => Hash::make('password123'),
            'role' => 'teknisi',
        ]);
        $matchingCustomer = Customer::create(['nama' => 'Pelanggan Cocok', 'no_wa' => '08123456789']);
        $otherCustomer = Customer::create(['nama' => 'Pelanggan Lain', 'no_wa' => '08123456780']);
        $matchingService = Service::create([
            'no_nota' => 'SRV-FILTER-001',
            'customer_id' => $matchingCustomer->id,
            'teknisi_id' => $technician->id,
            'merek_tipe' => 'Laptop Tes',
            'keluhan' => 'Tidak menyala',
        ]);
        Service::create([
            'no_nota' => 'SRV-FILTER-002',
            'customer_id' => $otherCustomer->id,
            'teknisi_id' => $technician->id,
            'merek_tipe' => 'Laptop Lain',
            'keluhan' => 'Layar gelap',
            'status' => 'dikerjakan',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/services-active?status=masuk&customer=Cocok&teknisi_id='.$technician->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchingService->id);
    }

    public function test_owner_report_includes_profit_receivables_and_export_formats(): void
    {
        $owner = User::create([
            'nama' => 'Owner Tes',
            'username' => 'owner_report_test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);
        $technician = User::create([
            'nama' => 'Teknisi Laporan',
            'username' => 'technician_report',
            'password' => Hash::make('password123'),
            'role' => 'teknisi',
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Laporan', 'no_wa' => '08123456789']);
        $part = Sparepart::create([
            'kode_barang' => 'RAM-REPORT',
            'nama_barang' => 'RAM Laporan',
            'stok' => 3,
            'harga_beli' => 200,
            'harga_jual' => 400,
        ]);
        $service = Service::create([
            'no_nota' => 'SRV-REPORT-001',
            'customer_id' => $customer->id,
            'teknisi_id' => $technician->id,
            'merek_tipe' => 'Laptop Laporan',
            'keluhan' => 'Tidak menyala',
            'status' => 'dikerjakan',
            'total_biaya' => 1000,
        ]);
        Payment::create([
            'service_id' => $service->id,
            'amount' => 250,
            'method' => 'tunai',
            'paid_at' => now(),
            'received_by' => $owner->id,
            'type' => 'payment',
        ]);
        $service->syncPaymentSummary();
        ServiceItem::create([
            'service_id' => $service->id,
            'sparepart_id' => $part->id,
            'jumlah' => 2,
            'harga_modal' => 200,
            'harga_satuan' => 400,
            'subtotal' => 800,
        ]);
        $date = now()->toDateString();
        $filters = '?date_from='.$date.'&date_to='.$date.'&period=month';

        $this->actingAs($owner)->getJson('/api/owner/reports?date_from='.$date.'&date_to='.$date.'&period=day')
            ->assertOk()
            ->assertJsonPath('data.series.0.jumlah_servis', 1)
            ->assertJsonPath('data.status_counts.0.jumlah', 1);

        $this->getJson('/api/owner/reports'.$filters)
            ->assertOk()
            ->assertJsonPath('data.summary.omzet', 250)
            ->assertJsonPath('data.summary.modal_sparepart', 100)
            ->assertJsonPath('data.summary.laba_kotor', 150)
            ->assertJsonPath('data.summary.piutang', 750)
            ->assertJsonPath('data.technician_counts.0.jumlah_servis', 1)
            ->assertJsonPath('data.technician_counts.0.omzet', 250)
            ->assertJsonPath('data.top_parts.0.jumlah_terjual', 2)
            ->assertJsonPath('data.receivables.0.terbayar', 250)
            ->assertJsonPath('data.receivables.0.piutang', 750)
            ->assertJsonPath('data.cash_summary.0.tunai', 250);

        $csv = $this->get('/api/owner/reports.csv'.$filters)->assertOk();
        $this->assertStringContainsString('Laba kotor', $csv->streamedContent());
        $this->assertStringContainsString('Rekonsiliasi kas harian per kasir', $csv->streamedContent());
        $this->assertStringContainsString('SRV-REPORT-001', $csv->streamedContent());

        $this->get('/api/owner/reports/print'.$filters)
            ->assertOk()
            ->assertSee('Laporan Operasional TECKFO Service')
            ->assertSee('Rekonsiliasi kas harian per kasir')
            ->assertSee('RAM Laporan')
            ->assertSee('Pelanggan Laporan');
    }

    public function test_report_groups_a_service_created_at_0030_wib_on_its_local_calendar_date(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $createdAt = Carbon::parse('2026-11-22 00:30:00', 'Asia/Jakarta');
        Carbon::setTestNow($createdAt);

        try {
            $owner = User::factory()->owner()->create();
            $customer = Customer::create(['nama' => 'Pelanggan Tengah Malam', 'no_wa' => '08123456789']);
            $service = Service::create([
                'no_nota' => 'SRV-WIB-0030',
                'customer_id' => $customer->id,
                'merek_tipe' => 'Laptop WIB',
                'keluhan' => 'Pengecekan waktu laporan',
                'total_biaya' => 125000,
                'created_at' => $createdAt,
            ]);

            $this->assertSame(
                '2026-11-22 00:30:00',
                DB::table('services')->where('id', $service->id)->value('created_at')
            );

            $this->actingAs($owner)
                ->getJson('/api/owner/reports?date_from=2026-11-22&date_to=2026-11-22&period=day')
                ->assertOk()
                ->assertJsonPath('data.summary.jumlah_servis', 1)
                ->assertJsonPath('data.series.0.label', '22 Nov')
                ->assertJsonPath('data.series.0.jumlah_servis', 1);
            $this->getJson('/api/owner/reports?date_from=2026-11-22&date_to=2026-11-22&period=month')
                ->assertOk()
                ->assertJsonPath('data.series.0.jumlah_servis', 1);

            $this->getJson('/api/owner/reports?date_from=2026-11-21&date_to=2026-11-21&period=day')
                ->assertOk()
                ->assertJsonPath('data.summary.jumlah_servis', 0);

            $csv = $this->get('/api/owner/reports.csv?date_from=2026-11-22&date_to=2026-11-22&period=day')
                ->assertOk();
            $this->assertStringContainsString('22 Nov', $csv->streamedContent());
        } finally {
            Carbon::setTestNow();
        }
    }
}
