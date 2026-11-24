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

class PaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_installments_refunds_reports_and_daily_cash_reconciliation_use_payment_dates(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $cashier = User::create([
            'nama' => 'Kasir Rekonsiliasi',
            'username' => 'kasir_ledger',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $owner = User::create([
            'nama' => 'Owner Ledger',
            'username' => 'owner_ledger',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'must_change_password' => false,
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Ledger', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-LEDGER-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Ledger',
            'keluhan' => 'Pengecekan pembayaran',
            'status' => 'selesai',
            'total_biaya' => 1000,
        ]);
        DB::table('services')->where('id', $service->id)->update([
            'created_at' => '2026-11-30 10:00:00',
        ]);
        $part = Sparepart::create([
            'kode_barang' => 'LEDGER-PART',
            'nama_barang' => 'Sparepart Ledger',
            'stok' => 1,
            'harga_beli' => 500,
            'harga_jual' => 500,
        ]);
        ServiceItem::create([
            'service_id' => $service->id,
            'sparepart_id' => $part->id,
            'jumlah' => 1,
            'harga_modal' => 500,
            'harga_satuan' => 500,
            'subtotal' => 500,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-30 20:30:00', 'Asia/Jakarta'));
        $this->actingAs($cashier)->postJson('/api/admin/service/payments', [
            'service_id' => $service->id,
            'amount' => 400,
            'method' => 'tunai',
            'status_pembayaran' => 'lunas',
        ])->assertOk()->assertJsonPath('data.status_pembayaran', 'sebagian');
        $this->assertSame('sebagian', $service->fresh()->status_pembayaran);
        $this->actingAs($cashier)->putJson('/api/admin/service/pelunasan', [
            'service_id' => $service->id,
            'amount' => 1,
            'method' => 'tunai',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('selesai', $service->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-12-01 09:15:00', 'Asia/Jakarta'));
        $this->actingAs($cashier)->postJson('/api/admin/service/payments', [
            'service_id' => $service->id,
            'amount' => 600,
            'method' => 'transfer',
        ])->assertOk()->assertJsonPath('data.status_pembayaran', 'lunas');

        Carbon::setTestNow(Carbon::parse('2026-12-02 11:45:00', 'Asia/Jakarta'));
        $this->actingAs($cashier)->postJson('/api/admin/service/payments', [
            'service_id' => $service->id,
            'type' => 'refund',
            'amount' => 100,
            'method' => 'qris',
            'note' => 'Penyesuaian transaksi',
        ])->assertOk()
            ->assertJsonPath('data.status_pembayaran', 'sebagian')
            ->assertJsonPath('data.piutang', 100);
        $this->postJson('/api/admin/service/payments', [
            'service_id' => $service->id,
            'type' => 'refund',
            'amount' => 901,
            'method' => 'tunai',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 3);

        $reportUrl = '/api/owner/reports?date_from=2026-11-30&date_to=2026-12-02&period=day';
        $report = $this->actingAs($owner)->getJson($reportUrl)
            ->assertOk()
            ->assertJsonPath('data.summary.omzet', 900)
            ->assertJsonPath('data.summary.modal_sparepart', 450)
            ->assertJsonPath('data.summary.laba_kotor', 450)
            ->assertJsonPath('data.summary.piutang', 100)
            ->assertJsonPath('data.series.0.omzet', 400)
            ->assertJsonPath('data.series.1.omzet', 600)
            ->assertJsonPath('data.series.2.omzet', -100)
            ->assertJsonPath('data.cash_summary.0.tunai', 400)
            ->assertJsonPath('data.cash_summary.1.non_tunai', 600)
            ->assertJsonPath('data.cash_summary.2.non_tunai', -100);
        $this->assertSame('2026-11-30', $report->json('data.cash_summary.0.tanggal'));

        $this->getJson(str_replace('period=day', 'period=month', $reportUrl))
            ->assertOk()
            ->assertJsonCount(2, 'data.series')
            ->assertJsonPath('data.series.0.omzet', 400)
            ->assertJsonPath('data.series.1.omzet', 500);
    }

    public function test_legacy_payment_import_is_idempotent_and_uses_payment_activity_date_and_cashier(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $cashier = User::create([
            'nama' => 'Kasir Lama',
            'username' => 'kasir_legacy',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Lama', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-LEGACY-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Lama',
            'keluhan' => 'Servis lama',
            'status' => 'diambil',
            'total_biaya' => 1000,
        ]);
        DB::table('services')->where('id', $service->id)->update([
            'status_pembayaran' => 'dp',
            'metode_pembayaran' => 'Cash',
            'dp_dibayar' => 250,
            'nominal_dibayar' => 270,
        ]);
        DB::table('activity_logs')->insert([
            'user_id' => $cashier->id,
            'aksi' => 'pembayaran',
            'subjek' => 'service:'.$service->id,
            'created_at' => '2026-12-03 14:25:00',
        ]);

        $this->artisan('payments:migrate-legacy')->assertSuccessful();
        $payment = Payment::sole();
        $this->assertSame(250, $payment->amount);
        $this->assertSame('tunai', $payment->method);
        $this->assertSame('payment', $payment->type);
        $this->assertSame('migrasi data lama', $payment->note);
        $this->assertSame($cashier->id, $payment->received_by);
        $this->assertSame('2026-12-03 14:25:00', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertSame('sebagian', $service->fresh()->status_pembayaran);

        $this->artisan('payments:migrate-legacy')->assertSuccessful();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_legacy_payment_import_uses_handover_date_before_service_updated_at(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $customer = Customer::create(['nama' => 'Pelanggan Serah Terima', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-LEGACY-HANDOVER',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Lama',
            'keluhan' => 'Servis lama',
            'status' => 'diambil',
            'total_biaya' => 1000,
        ]);
        DB::table('services')->where('id', $service->id)->update([
            'status_pembayaran' => 'lunas',
            'nominal_dibayar' => 1000,
            'updated_at' => '2026-12-10 14:25:00',
            'created_at' => '2026-11-30 14:25:00',
        ]);
        DB::table('service_status_logs')->insert([
            'service_id' => $service->id,
            'status_lama' => 'selesai',
            'status_baru' => 'diambil',
            'created_at' => '2026-12-03 14:25:00',
        ]);

        $this->artisan('payments:migrate-legacy')->assertSuccessful();

        $payment = Payment::sole();
        $this->assertSame(1000, $payment->amount);
        $this->assertSame('2026-12-03 14:25:00', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertSame('migrasi data lama', $payment->note);
    }
}
