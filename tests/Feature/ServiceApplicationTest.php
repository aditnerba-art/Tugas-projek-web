<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceItem;
use App\Models\ServiceLog;
use App\Models\ServiceStatusLog;
use App\Models\Sparepart;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
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

    public function test_admin_reuses_existing_customer_when_customer_id_is_selected(): void
    {
        $admin = User::create([
            'nama' => 'Admin Lama',
            'username' => 'admin_lama',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $customer = Customer::create([
            'nama' => 'Pelanggan Lama',
            'no_wa' => '08123450000',
        ]);

        $this->actingAs($admin)->postJson('/api/admin/service', [
            'customer_id' => $customer->id,
            'nama' => 'Pelanggan Lama',
            'no_wa' => '08123450000',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'Lenovo Lama',
            'keluhan' => 'Servis kedua',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('services', [
            'customer_id' => $customer->id,
            'merek_tipe' => 'Lenovo Lama',
        ]);

        $this->getJson('/api/admin/customers?q=Pelanggan Lama')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('data.0.jumlah_servis', 1);
    }

    public function test_customer_deletion_uses_soft_delete_and_preserves_service_history(): void
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

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('service_items', ['id' => $item->id]);
        $this->assertDatabaseHas('service_logs', ['id' => $log->id]);
        $this->assertDatabaseHas('service_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseCount('customer_sessions', 1);
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
        $this->get('/index.html')->assertStatus(301)->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('name="csrf-token"', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/lacak')->assertOk();
        $this->get('/missing-page')->assertNotFound()->assertSee('Halaman Tidak Ditemukan');
        $this->get('/owner/activity-logs')->assertForbidden()->assertSee('Akses Ditolak');
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
        $this->get('/owner/activity-logs')->assertOk()->assertSee('Log Aktivitas');

        $staffResponse = $this->postJson('/api/owner/staff', [
            'nama' => 'Teknisi Baru',
            'username' => 'teknisi_baru',
            'password' => 'password123',
            'role' => 'teknisi',
        ])->assertCreated();
        $staffId = $staffResponse->json('user.id');
        $this->patchJson('/api/owner/staff/'.$staffId, ['nama' => 'Teknisi Diperbarui'])
            ->assertOk()->assertJsonPath('user.nama', 'Teknisi Diperbarui');
        $this->patchJson('/api/owner/staff/'.$staffId, ['newPassword' => 'reset-password-123'])
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $staffId, 'must_change_password' => true]);

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
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'ubah_stok', 'user_id' => $owner->id]);
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
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'pembayaran',
            'user_id' => $admin->id,
            'subjek' => 'service:'.$service->id,
        ]);
        $this->assertDatabaseHas('service_status_logs', [
            'service_id' => $service->id,
            'status_lama' => 'selesai',
            'status_baru' => 'diambil',
            'user_id' => $admin->id,
        ]);
    }

    public function test_service_status_changes_are_logged_once_through_the_model_method_and_support_etag_polling(): void
    {
        $admin = User::create([
            'nama' => 'Admin Status',
            'username' => 'admin_status',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Status', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-STATUS-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Status',
            'keluhan' => 'Tidak menyala',
        ]);

        $service->changeStatus('masuk', $admin, 'Servis diterima');
        $service->changeStatus('diperiksa', $admin, 'Pemeriksaan dimulai');
        $service->changeStatus('diperiksa', $admin, 'Tidak membuat duplikat');

        $this->assertSame(2, ServiceStatusLog::where('service_id', $service->id)->count());
        $this->assertDatabaseHas('service_status_logs', [
            'service_id' => $service->id,
            'status_lama' => null,
            'status_baru' => 'masuk',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('service_status_logs', [
            'service_id' => $service->id,
            'status_lama' => 'masuk',
            'status_baru' => 'diperiksa',
            'catatan' => 'Pemeriksaan dimulai',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin);
        $firstResponse = $this->getJson('/api/services/status-updates')->assertOk();
        $etag = $firstResponse->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->getJson('/api/services/status-updates', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_customer_approval_creates_admin_notification(): void
    {
        $admin = User::create([
            'nama' => 'Admin Notifikasi',
            'username' => 'admin_notification',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Notifikasi', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-NOTIFY-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'status' => 'menunggu_persetujuan',
        ]);
        ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '61384', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/customer/login', [
            'kode_akses' => '61384',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'status_baru' => 'setuju',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => \App\Notifications\ServicePanelNotification::class,
        ]);
    }

    public function test_customer_realtime_channel_key_is_only_disclosed_to_a_valid_portal_session(): void
    {
        $customer = Customer::create(['nama' => 'Pelanggan Realtime', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-REALTIME-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Tidak menyala',
        ]);
        $token = ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '74519', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);
        $channelKey = $service->fresh()->customerStatusChannelKey();
        $this->assertNotEmpty($channelKey);

        $this->getJson('/api/tracking?kode_akses=74519')->assertOk()
            ->assertJsonMissingPath('data.unit.status_channel_key');

        $this->postJson('/api/customer/login', [
            'kode_akses' => '74519',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->getJson('/api/customer/me')->assertOk()
            ->assertJsonPath('data.unit.status_channel_key', $channelKey);

        $token->update(['revoked_at' => now()]);
        $this->getJson('/api/customer/me')->assertUnauthorized();
    }

    public function test_staff_realtime_channels_are_role_scoped(): void
    {
        Broadcast::setDefaultDriver('reverb');
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $admin = User::create([
            'nama' => 'Admin Channel',
            'username' => 'admin_channel',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $technician = User::create([
            'nama' => 'Teknisi Channel',
            'username' => 'teknisi_channel',
            'password' => Hash::make('password123'),
            'role' => 'teknisi',
        ]);

        $this->actingAs($admin)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-staff.services',
        ])->assertOk();
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-staff.technician.'.$technician->id,
        ])->assertForbidden();

        $this->actingAs($technician)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-staff.technician.'.$technician->id,
        ])->assertOk();
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-staff.services',
        ])->assertForbidden();
    }

    public function test_customer_access_code_attempts_are_locked_per_ip_and_return_a_clear_message(): void
    {
        $ip = '203.0.113.71';
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/customer/login', [
                'kode_akses' => (string) (51000 + $attempt),
                'customer_name' => 'Nama tidak cocok',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/customer/login', [
            'kode_akses' => '51999',
            'customer_name' => 'Nama tidak cocok',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak percobaan kode akses. Coba kembali dalam 10 menit.');

        $this->travel(11)->minutes();
        $this->postJson('/api/customer/login', [
            'kode_akses' => '51999',
            'customer_name' => 'Nama tidak cocok',
        ])->assertUnauthorized();
        $this->travelBack();
    }

    public function test_customer_access_code_attempts_are_locked_per_code_across_ips(): void
    {
        $code = '58321';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.(80 + $attempt)])
                ->postJson('/api/customer/login', [
                    'kode_akses' => $code,
                    'customer_name' => 'Nama tidak cocok',
                ])->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])
            ->postJson('/api/customer/login', [
                'kode_akses' => $code,
                'customer_name' => 'Nama tidak cocok',
            ])->assertTooManyRequests();
    }

    public function test_staff_login_is_limited_per_username_and_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.101']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'username' => 'rate_limit_user',
                'password' => 'wrong-password',
                'role' => 'admin',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'username' => 'rate_limit_user',
            'password' => 'wrong-password',
            'role' => 'admin',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak percobaan login. Coba kembali dalam satu menit.');
    }

    public function test_customer_approval_requires_session_and_technicians_can_only_approve_assigned_services(): void
    {
        $customer = Customer::create(['nama' => 'Pelanggan Uji', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-APPROVAL-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Uji',
            'keluhan' => 'Tidak menyala',
            'status' => 'menunggu_persetujuan',
        ]);
        $token = ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '54321', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);
        $technician = User::create([
            'nama' => 'Teknisi Uji',
            'username' => 'teknisi_unassigned',
            'password' => Hash::make('password123'),
            'role' => 'teknisi',
        ]);

        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'kode_akses' => '54321',
            'status_baru' => 'dikerjakan',
        ])->assertUnauthorized();

        $this->actingAs($technician)->postJson('/api/admin/service/persetujuan', [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
        ])->assertForbidden();

        $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'menunggu_persetujuan']);
        $this->assertDatabaseHas('service_access_tokens', ['id' => $token->id]);
        $service->update(['teknisi_id' => $technician->id]);
        $this->postJson('/api/admin/service/persetujuan', [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
        ])->assertOk();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'dikerjakan']);
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'ubah_status_servis',
            'subjek' => 'service:'.$service->id,
            'user_id' => $technician->id,
        ]);
    }

    public function test_seed_style_account_must_change_password_before_using_staff_routes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue($user->must_change_password);

        $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
            'role' => 'admin',
        ])->assertOk()->assertJsonPath('user.must_change_password', true);
        $this->getJson('/api/admin/customers')->assertForbidden();
        $this->get('/password/change')->assertOk()->assertSee('Ubah Kata Sandi');

        $this->postJson('/api/auth/password', [
            'current_password' => 'admin123',
            'password' => 'a-new-password-123',
            'password_confirmation' => 'a-new-password-123',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->getJson('/api/admin/customers')->assertOk();
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'login',
            'subjek' => 'user:'.$user->id,
            'ip' => '127.0.0.1',
        ]);
    }
}
