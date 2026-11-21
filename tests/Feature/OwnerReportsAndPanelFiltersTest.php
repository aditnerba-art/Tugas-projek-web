<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status_pembayaran' => 'dp',
            'dp_dibayar' => 250,
        ]);
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

        $this->actingAs($owner)
            ->getJson('/api/owner/reports'.$filters)
            ->assertOk()
            ->assertJsonPath('data.summary.omzet', 1000)
            ->assertJsonPath('data.summary.modal_sparepart', 400)
            ->assertJsonPath('data.summary.laba_kotor', 600)
            ->assertJsonPath('data.summary.piutang', 750)
            ->assertJsonPath('data.technician_counts.0.jumlah_servis', 1)
            ->assertJsonPath('data.top_parts.0.jumlah_terjual', 2)
            ->assertJsonPath('data.receivables.0.piutang', 750);

        $csv = $this->get('/api/owner/reports.csv'.$filters)->assertOk();
        $this->assertStringContainsString('Laba kotor', $csv->streamedContent());
        $this->assertStringContainsString('SRV-REPORT-001', $csv->streamedContent());

        $this->get('/api/owner/reports/print'.$filters)
            ->assertOk()
            ->assertSee('Laporan Operasional TECKFO Service')
            ->assertSee('RAM Laporan')
            ->assertSee('Pelanggan Laporan');
    }
}
