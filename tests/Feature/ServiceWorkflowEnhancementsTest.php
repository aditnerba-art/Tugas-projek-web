<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServicePhoto;
use App\Models\ServiceRate;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceWorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::create([
            'nama' => ucfirst($role).' Tes',
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'must_change_password' => false,
        ]);
    }

    private function service(array $attributes = []): Service
    {
        $customer = Customer::create(['nama' => 'Pelanggan Tes', 'no_wa' => '08123456789']);

        return Service::create(array_merge([
            'no_nota' => 'SRV-WORK-'.random_int(10000, 99999),
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop Tes',
            'keluhan' => 'Tidak menyala',
        ], $attributes));
    }

    public function test_estimate_changes_are_added_to_timeline_and_notify_customer(): void
    {
        $admin = $this->user('admin', 'admin_estimate');
        $rate = ServiceRate::create(['name' => 'Diagnosa', 'amount' => 150000, 'active' => true]);
        $service = $this->service([
            'status' => 'menunggu_persetujuan',
            'total_biaya' => 100000,
            'biaya_jasa' => 100000,
            'estimated_completion_at' => today()->addDays(3),
        ]);

        $this->actingAs($admin)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'menunggu_persetujuan',
            'service_rate_id' => $rate->id,
            'estimated_completion_at' => today()->addDays(5)->toDateString(),
        ])->assertOk();

        $log = NotificationLog::sole();
        $this->assertSame('cost_approval', $log->message_type);
        $this->assertStringContainsString('150.000', $log->message);
        $this->assertStringContainsString(today()->addDays(5)->format('d-m-Y'), $log->message);
        $this->assertDatabaseHas('service_status_logs', [
            'service_id' => $service->id,
            'status_lama' => 'menunggu_persetujuan',
            'status_baru' => 'menunggu_persetujuan',
        ]);
    }

    public function test_sparepart_price_changes_require_customer_approval_and_stock_returns_when_rejected(): void
    {
        $technician = $this->user('teknisi', 'teknisi_stock');
        $service = $this->service(['teknisi_id' => $technician->id]);
        $part = Sparepart::create([
            'kode_barang' => 'SSD-TEST',
            'nama_barang' => 'SSD Test',
            'stok' => 3,
            'stok_minimum' => 1,
            'harga_beli' => 50000,
            'harga_jual' => 80000,
        ]);

        $this->actingAs($technician)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
            'sparepart_id' => $part->id,
            'jumlah_sparepart' => 2,
        ])->assertOk()->assertJsonPath('status_baru', 'menunggu_persetujuan');

        $this->assertDatabaseHas('spareparts', ['id' => $part->id, 'stok' => 1]);
        $this->assertDatabaseHas('service_items', [
            'service_id' => $service->id,
            'sparepart_id' => $part->id,
            'harga_modal' => 50000,
            'harga_satuan' => 80000,
            'subtotal' => 160000,
            'approval_status' => 'pending_approval',
        ]);
        $this->getJson('/api/spareparts')->assertOk()->assertJsonPath('data.0.stok_rendah', true);

        $this->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'menunggu_persetujuan',
            'sparepart_id' => $part->id,
            'jumlah_sparepart' => 2,
        ])->assertStatus(422);
        $this->assertDatabaseHas('spareparts', ['id' => $part->id, 'stok' => 1]);
        $this->assertDatabaseCount('service_items', 1);

        $this->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
        ])->assertStatus(409);

        $customer = $service->customer;
        ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '54321', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);
        auth()->logout();
        $this->postJson('/api/customer/login', [
            'kode_akses' => '54321',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'status_baru' => 'batal',
        ])->assertOk()->assertJsonPath('data.status_baru', 'batal');

        $this->assertDatabaseHas('spareparts', ['id' => $part->id, 'stok' => 3]);
        $this->assertDatabaseHas('service_items', [
            'service_id' => $service->id,
            'approval_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'pengembalian_stok',
            'subjek' => 'sparepart:'.$part->id,
        ]);
    }

    public function test_staff_cannot_submit_raw_prices_or_change_sparepart_prices(): void
    {
        $admin = $this->user('admin', 'admin_price_guard');
        $technician = $this->user('teknisi', 'tech_price_guard');
        $service = $this->service(['teknisi_id' => $technician->id]);
        $part = Sparepart::create([
            'kode_barang' => 'PRICE-GUARD',
            'nama_barang' => 'Sparepart Harga Tetap',
            'stok' => 2,
            'harga_beli' => 40000,
            'harga_jual' => 60000,
        ]);

        $this->actingAs($technician)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'diperiksa',
            'biaya_jasa' => 1,
        ])->assertUnprocessable();
        $this->actingAs($admin)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'diperiksa',
            'total_biaya' => 1,
        ])->assertUnprocessable();
        $this->putJson('/api/spareparts/'.$part->id, [
            'kode_barang' => $part->kode_barang,
            'nama_barang' => $part->nama_barang,
            'stok' => 2,
            'stok_minimum' => 5,
            'harga_jual' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('spareparts', [
            'id' => $part->id,
            'harga_beli' => 40000,
            'harga_jual' => 60000,
        ]);
    }

    public function test_owner_controls_service_rates_and_staff_can_only_read_active_rates(): void
    {
        $owner = $this->user('owner', 'owner_rates');
        $admin = $this->user('admin', 'admin_rates');
        $technician = $this->user('teknisi', 'tech_rates');

        $this->actingAs($admin)->postJson('/api/owner/service-rates', [
            'name' => 'Pembersihan',
            'amount' => 50000,
        ])->assertForbidden();

        $response = $this->actingAs($owner)->postJson('/api/owner/service-rates', [
            'name' => 'Pembersihan',
            'amount' => 50000,
        ])->assertCreated();
        $rateId = $response->json('data.id');

        $this->actingAs($technician)->getJson('/api/service-rates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $rateId)
            ->assertJsonPath('data.0.amount', 50000);
        $this->putJson('/api/owner/service-rates/'.$rateId, [
            'name' => 'Pembersihan',
            'amount' => 100000,
        ])->assertForbidden();

        $this->assertDatabaseHas('service_rates', ['id' => $rateId, 'amount' => 50000]);
    }

    public function test_warranty_starts_on_handover_and_public_tracking_link_shows_it(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        $admin = $this->user('admin', 'admin_warranty');
        $service = $this->service(['status' => 'selesai', 'warranty_days' => 30]);

        $this->actingAs($admin)->putJson('/api/admin/service/pelunasan', [
            'service_id' => $service->id,
            'metode_pembayaran' => 'Cash',
        ])->assertOk()->assertJsonPath('data.warranty_expires_at', today()->addDays(30)->toDateString());

        $this->get('/lacak/unit/'.$service->tracking_key)->assertOk();
        $this->getJson('/api/tracking-link/'.$service->tracking_key)
            ->assertOk()
            ->assertJsonPath('data.unit.warranty_active', true)
            ->assertJsonPath('data.unit.warranty_days', 30);
    }

    public function test_service_photos_are_validated_stored_and_visible_only_through_service_tracking(): void
    {
        Storage::fake('public');
        Storage::fake('service_photos');
        $admin = $this->user('admin', 'admin_photo');
        $technician = $this->user('teknisi', 'teknisi_photo');
        $service = $this->service(['teknisi_id' => $technician->id]);

        $this->actingAs($admin)->post('/api/admin/service/photos', [
            'service_id' => $service->id,
            'kind' => 'intake',
            'photos' => [UploadedFile::fake()->image('unit.png', 640, 480)->size(500)],
        ])->assertCreated();

        $photo = $service->photos()->sole();
        Storage::disk('service_photos')->assertExists($photo->path);
        Storage::disk('service_photos')->assertExists($photo->thumbnail_path);
        Storage::disk('public')->assertMissing($photo->path);
        Storage::disk('public')->assertMissing($photo->thumbnail_path);
        $tracking = $this->getJson('/api/tracking-link/'.$service->tracking_key)
            ->assertOk()
            ->assertJsonPath('data.photos.0.kind', 'intake');
        $photoUrl = $tracking->json('data.photos.0.url');
        $this->assertStringContainsString('tracking_key='.$service->tracking_key, $photoUrl);

        $photoPath = parse_url($photoUrl, PHP_URL_PATH);
        Auth::logout();
        $this->get($photoPath)->assertNotFound();
        $this->get('/storage/'.$photo->path)->assertForbidden();
        $this->get($photoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($admin)->get(route('service-photos.show', [
            'photo' => $photo->id,
            'variant' => 'thumbnail',
        ]))->assertOk();
        $this->actingAs($technician)->get(route('service-photos.show', [
            'photo' => $photo->id,
        ]))->assertOk();

        $otherTechnician = $this->user('teknisi', 'teknisi_other');
        $this->actingAs($otherTechnician)->get(route('service-photos.show', [
            'photo' => $photo->id,
        ]))->assertNotFound();
        $this->actingAs($otherTechnician)->withHeaders(['Accept' => 'application/json'])->post('/api/admin/service/photos', [
            'service_id' => $service->id,
            'kind' => 'completion',
            'photos' => [UploadedFile::fake()->image('other.jpg', 150, 150)],
        ])->assertNotFound();

        $this->actingAs($admin)->post('/api/admin/service/photos', [
            'service_id' => $service->id,
            'kind' => 'intake',
            'photos' => [UploadedFile::fake()->create('bad.svg', 100, 'image/svg+xml')],
        ])->assertUnprocessable();
    }

    public function test_private_photo_migration_supports_dry_run_and_reencodes_legacy_images(): void
    {
        Storage::fake('public');
        Storage::fake('service_photos');
        $service = $this->service();
        $image = UploadedFile::fake()->image('legacy.png', 320, 240);
        $legacyContents = file_get_contents($image->getRealPath()).'private-metadata-marker';
        $legacyPath = 'services/'.$service->id.'/legacy.jpg';
        $legacyThumbnailPath = 'services/'.$service->id.'/thumbs/legacy.jpg';
        Storage::disk('public')->put($legacyPath, $legacyContents);
        Storage::disk('public')->put($legacyThumbnailPath, file_get_contents($image->getRealPath()));
        $photo = ServicePhoto::create([
            'service_id' => $service->id,
            'uploaded_by' => null,
            'kind' => 'intake',
            'path' => $legacyPath,
            'thumbnail_path' => $legacyThumbnailPath,
            'mime_type' => 'image/png',
            'size' => strlen($legacyContents),
        ]);

        $this->artisan('service-photos:migrate-private', ['--dry-run' => true])
            ->expectsOutputToContain('1 foto, 0 gagal')
            ->assertSuccessful();
        $this->assertSame($legacyPath, $photo->fresh()->path);
        Storage::disk('public')->assertExists($legacyPath);
        Storage::disk('service_photos')->assertMissing('service-photos/'.$legacyPath);

        $this->artisan('service-photos:migrate-private')->assertSuccessful();
        $photo->refresh();
        $this->assertSame('service-photos/'.$legacyPath, $photo->path);
        $this->assertSame('image/jpeg', $photo->mime_type);
        Storage::disk('service_photos')->assertExists($photo->path);
        Storage::disk('service_photos')->assertExists($photo->thumbnail_path);
        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('public')->assertMissing($legacyThumbnailPath);
        $this->get('/storage/'.$legacyPath)->assertForbidden();
        $this->assertStringNotContainsString('private-metadata-marker', Storage::disk('service_photos')->get($photo->path));
    }

    public function test_admin_assignment_exposes_workload_and_technicians_only_see_their_services(): void
    {
        $admin = $this->user('admin', 'admin_assignment');
        $technician = $this->user('teknisi', 'teknisi_assignment');
        $otherTechnician = $this->user('teknisi', 'teknisi_assignment_other');

        $created = $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Tes',
            'no_wa' => '08123456789',
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Rusak',
            'priority' => 'urgent',
            'teknisi_id' => $technician->id,
            'accessories' => ['Charger', 'Tas'],
            'physical_condition' => 'Gores ringan',
        ])->assertOk();

        $this->getJson('/api/admin/teknisi')->assertOk()
            ->assertJsonPath('data.0.beban_kerja', 1);
        $this->actingAs($otherTechnician)->getJson('/api/admin/services-active')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($technician)->getJson('/api/admin/services-active')
            ->assertOk()->assertJsonPath('data.0.priority', 'urgent');

        $service = Service::findOrFail($created->json('service_id'));
        $this->actingAs($admin)->get('/api/admin/receipts/'.$service->id.'/intake?paper=58')
            ->assertOk()->assertSee($service->tracking_key)->assertSee('Thermal 58 mm');
    }
}
