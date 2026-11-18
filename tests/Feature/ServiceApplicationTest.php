<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceItem;
use App\Models\ServiceLog;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_service_and_customer_can_track_and_approve_with_five_digit_code(): void
    {
        $admin = User::create([
            'nama' => 'Admin Tes',
            'username' => 'admin_test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Tes',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'ThinkPad T14',
            'keluhan' => 'Tidak menyala',
        ])->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonStructure(['no_nota', 'service_id', 'customer_token']);

        $response = $this->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Tes 2',
            'no_wa' => '08123456780',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'ThinkPad T14',
            'keluhan' => 'Layar gelap',
        ])->assertOk();
        $code = $response->json('customer_token');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $service = Service::where('no_nota', $response->json('no_nota'))->firstOrFail();
        $service->update(['status' => 'menunggu_persetujuan']);

        $this->getJson('/api/tracking?kode_akses='.$code)
            ->assertOk()
            ->assertJsonPath('data.unit.no_nota', $service->no_nota);
        $this->postJson('/api/customer/login', ['kode_akses' => $code, 'customer_name' => 'Pelanggan Tes 2'])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'status_baru' => 'setuju',
        ])->assertOk();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'dikerjakan']);

        $rotated = $this->postJson('/api/admin/customers/access-codes/'.$service->id)->assertOk();
        $newCode = $rotated->json('data.kode_akses');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $newCode);
        $this->getJson('/api/tracking?kode_akses='.$code)->assertNotFound();
        $this->getJson('/api/tracking?kode_akses='.$newCode)->assertOk();
        $this->getJson('/api/customer/me')->assertUnauthorized();
    }

    public function test_customer_deletion_cascades_through_service_history_and_access_sessions(): void
    {
        $customer = Customer::create(['nama' => 'Pelanggan Hapus', 'no_wa' => '08123456789']);
        $part = Sparepart::create(['kode_barang' => 'RAM-1', 'nama_barang' => 'RAM', 'stok' => 3]);
        $service = Service::create([
            'no_nota' => 'SRV-TEST-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Tes',
            'keluhan' => 'Tidak menyala',
        ]);
        $item = ServiceItem::create([
            'service_id' => $service->id,
            'sparepart_id' => $part->id,
            'jumlah' => 1,
            'harga_satuan' => 100,
            'subtotal' => 100,
        ]);
        $log = ServiceLog::create(['service_id' => $service->id, 'status_baru' => 'masuk']);
        $token = ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash('sha256', 'test-token'),
            'expires_at' => now()->addDay(),
        ]);
        $token->customerSessions()->create([
            'session_hash' => hash('sha256', 'test-session'),
            'expires_at' => now()->addDay(),
        ]);

        $customer->delete();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('service_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('service_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('service_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseCount('customer_sessions', 0);
    }

    public function test_role_middleware_blocks_other_roles_and_laravel_views_include_csrf_support(): void
    {
        $technician = User::create([
            'nama' => 'Teknisi Tes',
            'username' => 'teknisi_test',
            'password' => Hash::make('password123'),
            'role' => 'teknisi',
        ]);
        $this->actingAs($technician)->getJson('/api/admin/customers')->assertForbidden();
        $this->get('/index.html')->assertOk()->assertSee('name="csrf-token"', false);
        auth()->logout();
        $this->get('/admin.html')->assertRedirect('/admin-login.html');
        $this->getJson('/api/admin/services-active')->assertUnauthorized();
    }

    public function test_owner_can_manage_staff_inventory_and_record_a_purchase(): void
    {
        $owner = User::create([
            'nama' => 'Owner Tes',
            'username' => 'owner_test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);
        $this->actingAs($owner);

        $staffResponse = $this->postJson('/api/owner/staff', [
            'nama' => 'Teknisi Baru',
            'username' => 'teknisi_baru',
            'password' => 'password123',
            'role' => 'teknisi',
        ])->assertCreated();
        $staffId = $staffResponse->json('user.id');
        $this->patchJson('/api/owner/staff/'.$staffId, ['nama' => 'Teknisi Diperbarui'])
            ->assertOk()->assertJsonPath('user.nama', 'Teknisi Diperbarui');

        $partResponse = $this->postJson('/api/spareparts', [
            'kode_barang' => 'RAM-8GB',
            'nama_barang' => 'RAM 8GB',
            'stok' => 2,
            'harga_beli' => 300000,
            'harga_jual' => 400000,
        ])->assertOk();
        $partId = $partResponse->json('data.id');
        $this->postJson('/api/owner/purchases', [
            'supplier' => 'Supplier Tes',
            'tanggal_pembelian' => '2026-11-18',
            'items' => [[
                'sparepart_id' => $partId,
                'jumlah' => 3,
                'harga_beli_satuan' => 310000,
            ]],
        ])->assertOk()->assertJsonPath('data.total_items', 1);

        $this->assertDatabaseHas('spareparts', ['id' => $partId, 'stok' => 5, 'harga_beli' => 310000]);
        $this->getJson('/api/owner/dashboard')->assertOk()
            ->assertJsonPath('data.purchases.total_transaksi_pengadaan', 1);
        $this->deleteJson('/api/owner/staff/'.$staffId)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $staffId]);
    }

    public function test_admin_cashier_payment_records_change_and_unit_handover(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kasir',
            'username' => 'kasir_test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Kasir', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-CASH-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Tes',
            'keluhan' => 'Tidak menyala',
            'status' => 'selesai',
            'total_biaya' => 100000,
        ]);

        $this->actingAs($admin)->putJson('/api/admin/service/pelunasan', [
            'service_id' => $service->id,
            'nominal_dibayar' => 125000,
        ])->assertOk()->assertJsonPath('data.kembalian', 25000)
            ->assertJsonPath('data.status_unit', 'diambil');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'diambil',
            'status_pembayaran' => 'lunas',
            'kembalian' => 25000,
        ]);
    }
}
