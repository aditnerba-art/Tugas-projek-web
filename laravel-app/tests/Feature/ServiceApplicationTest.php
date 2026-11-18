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

        $this->getJson('/api/tracking/'.$code)
            ->assertOk()
            ->assertJsonPath('data.unit.no_nota', $service->no_nota);
        $this->postJson('/api/customer/login', ['token' => $code, 'customer_name' => 'Pelanggan Tes 2'])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'status_baru' => 'setuju',
        ])->assertOk();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'dikerjakan']);
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
    }
}
