<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceEstimateChange;
use App\Models\ServiceItem;
use App\Models\ServicePhoto;
use App\Models\ServiceRate;
use App\Models\Sparepart;
use App\Models\User;
use App\Models\WarrantyPreset;
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
        $customer = Customer::create(['nama' => 'Pelanggan Estimasi', 'no_wa' => '08123456789']);
        $service = $this->service([
            'customer_id' => $customer->id,
            'total_biaya' => 100000,
            'biaya_jasa' => 100000,
            'estimated_completion_at' => today()->addDays(3),
            'initial_estimated_completion_at' => today()->addDays(3),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/service/estimate-changes', [
            'service_id' => $service->id,
            'additional_days' => 2,
            'reason' => 'Sparepart belum tersedia',
        ])->assertCreated();
        $changeId = $response->json('data.id');
        $this->assertDatabaseHas('service_estimate_changes', [
            'id' => $changeId,
            'status' => 'pending',
        ]);
        $this->assertSame(
            today()->addDays(5)->toDateString(),
            ServiceEstimateChange::findOrFail($changeId)->proposed_estimated_completion_at->toDateString(),
        );
        $this->assertSame(today()->addDays(3)->toDateString(), $service->fresh()->estimated_completion_at->toDateString());
        $notification = NotificationLog::sole();
        $this->assertSame('estimate_approval', $notification->message_type);
        $this->assertStringContainsString('Sparepart belum tersedia', $notification->message);
        $this->assertStringContainsString(today()->addDays(5)->format('d-m-Y'), $notification->message);
        $this->getJson('/api/tracking-link/'.$service->tracking_key)
            ->assertOk()
            ->assertJsonPath('data.unit.pending_estimate_change.id', $changeId)
            ->assertJsonPath('data.unit.pending_estimate_change.reason', 'Sparepart belum tersedia');
        $this->assertDatabaseHas('service_status_logs', [
            'service_id' => $service->id,
            'catatan' => 'Usulan perubahan estimasi: '.today()->addDays(3)->format('d-m-Y')
                .' menjadi '.today()->addDays(5)->format('d-m-Y').' (+2 hari). Alasan: Sparepart belum tersedia. Menunggu persetujuan pelanggan.',
        ]);
        $this->postJson('/api/admin/service/estimate-changes', [
            'service_id' => $service->id,
            'additional_days' => 3,
            'reason' => 'Usulan duplikat saat masih menunggu',
        ])->assertStatus(409);

        ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '54321', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);
        $this->postJson('/api/customer/estimate-approval', [
            'estimate_change_id' => $changeId,
            'decision' => 'approved',
        ])->assertForbidden();
        auth()->logout();
        $this->postJson('/api/customer/login', [
            'kode_akses' => '54321',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->postJson('/api/customer/estimate-approval', [
            'estimate_change_id' => $changeId,
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(today()->addDays(5)->toDateString(), $service->fresh()->estimated_completion_at->toDateString());
        $this->assertSame(today()->addDays(3)->toDateString(), $service->fresh()->initial_estimated_completion_at->toDateString());
        $secondChange = $this->actingAs($admin)->postJson('/api/admin/service/estimate-changes', [
            'service_id' => $service->id,
            'additional_days' => 3,
            'reason' => 'Pemeriksaan tambahan diperlukan',
        ])->assertCreated();
        auth()->logout();
        $this->postJson('/api/customer/login', [
            'kode_akses' => '54321',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->postJson('/api/customer/estimate-approval', [
            'estimate_change_id' => $secondChange->json('data.id'),
            'decision' => 'rejected',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame(today()->addDays(5)->toDateString(), $service->fresh()->estimated_completion_at->toDateString());
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

    public function test_technician_can_add_multiple_spareparts_atomically_and_cancel_an_unneeded_item(): void
    {
        $technician = $this->user('teknisi', 'teknisi_multi_parts');
        $service = $this->service([
            'teknisi_id' => $technician->id,
            'biaya_jasa' => 100000,
            'total_biaya' => 100000,
        ]);
        $ssd = Sparepart::create([
            'kode_barang' => 'SSD-MULTI',
            'nama_barang' => 'SSD Multi',
            'stok' => 4,
            'stok_minimum' => 1,
            'harga_beli' => 50000,
            'harga_jual' => 80000,
        ]);
        $ram = Sparepart::create([
            'kode_barang' => 'RAM-MULTI',
            'nama_barang' => 'RAM Multi',
            'stok' => 1,
            'stok_minimum' => 1,
            'harga_beli' => 30000,
            'harga_jual' => 60000,
        ]);
        $payload = [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
            'sparepart_items' => [
                ['sparepart_id' => $ssd->id, 'jumlah' => 2],
                ['sparepart_id' => $ram->id, 'jumlah' => 2],
            ],
        ];

        $this->actingAs($technician)->putJson('/api/admin/service/status', $payload)
            ->assertUnprocessable();
        $this->assertDatabaseHas('spareparts', ['id' => $ssd->id, 'stok' => 4]);
        $this->assertDatabaseCount('service_items', 0);

        $payload['sparepart_items'][1]['jumlah'] = 1;
        $this->putJson('/api/admin/service/status', $payload)
            ->assertOk()
            ->assertJsonPath('status_baru', 'menunggu_persetujuan')
            ->assertJsonPath('total_biaya', 320000);
        $this->assertDatabaseHas('spareparts', ['id' => $ssd->id, 'stok' => 2]);
        $this->assertDatabaseHas('spareparts', ['id' => $ram->id, 'stok' => 0]);
        $this->assertDatabaseHas('service_items', [
            'service_id' => $service->id,
            'sparepart_id' => $ssd->id,
            'harga_modal' => 50000,
            'harga_satuan' => 80000,
            'subtotal' => 160000,
            'approval_status' => 'pending_approval',
        ]);
        $ssdItem = ServiceItem::where('service_id', $service->id)->where('sparepart_id', $ssd->id)->sole();

        $otherTechnician = $this->user('teknisi', 'teknisi_other_multi_parts');
        $this->actingAs($otherTechnician)->postJson('/api/admin/service/items/'.$ssdItem->id.'/cancel')
            ->assertNotFound();
        $this->assertDatabaseHas('spareparts', ['id' => $ssd->id, 'stok' => 2]);

        $this->actingAs($technician)->postJson('/api/admin/service/items/'.$ssdItem->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.total_biaya', 160000);
        $this->assertDatabaseHas('spareparts', ['id' => $ssd->id, 'stok' => 4]);
        $this->assertDatabaseHas('service_items', ['id' => $ssdItem->id, 'approval_status' => 'cancelled']);
        $this->assertSame(160000, $service->fresh()->total_biaya);
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'pengembalian_stok',
            'subjek' => 'sparepart:'.$ssd->id,
        ]);

        $tracking = $this->getJson('/api/tracking-link/'.$service->tracking_key)->assertOk();
        $this->assertSame(
            [$ram->id],
            collect($tracking->json('data.onderdil'))->pluck('sparepart_id')->all(),
        );
        $this->assertTrue(collect($tracking->json('data.timeline'))
            ->contains(fn (array $entry): bool => str_contains($entry['catatan'] ?? '', 'SSD Multi tidak jadi dipasang')));
    }

    public function test_sparepart_inventory_prioritizes_unsafe_stock_and_flags_recent_usage_risk(): void
    {
        $admin = $this->user('admin', 'admin_stock_risk');
        $service = $this->service();
        $riskPart = Sparepart::create([
            'kode_barang' => 'LOW-USED',
            'nama_barang' => 'Stok Rendah Sering Dipakai',
            'stok' => 3,
            'stok_minimum' => 4,
            'harga_beli' => 10000,
            'harga_jual' => 15000,
        ]);
        $safePart = Sparepart::create([
            'kode_barang' => 'SAFE-STOCK',
            'nama_barang' => 'Stok Aman',
            'stok' => 8,
            'stok_minimum' => 2,
            'harga_beli' => 20000,
            'harga_jual' => 30000,
        ]);
        ServiceItem::create([
            'service_id' => $service->id,
            'sparepart_id' => $riskPart->id,
            'jumlah' => 5,
            'harga_modal' => 10000,
            'harga_satuan' => 15000,
            'subtotal' => 75000,
            'approval_status' => 'installed',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/spareparts?sort=stok_rendah&direction=asc')
            ->assertOk();
        $this->assertSame($riskPart->id, $response->json('data.0.id'));
        $this->assertSame(5, $response->json('data.0.pemakaian_30_hari'));
        $this->assertTrue($response->json('data.0.risiko_pengadaan'));
        $this->assertSame($safePart->id, $response->json('data.1.id'));
        $this->assertSame(0, $response->json('data.1.pemakaian_30_hari'));
        $this->assertFalse($response->json('data.1.risiko_pengadaan'));
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
        $this->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'diperiksa',
            'estimated_completion_at' => today()->addDays(10)->toDateString(),
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

    public function test_owner_controls_warranty_presets_and_services_keep_the_selected_duration_snapshot(): void
    {
        $owner = $this->user('owner', 'owner_warranty_presets');
        $admin = $this->user('admin', 'admin_warranty_presets');
        $technician = $this->user('teknisi', 'tech_warranty_presets');

        $this->actingAs($admin)->postJson('/api/owner/warranty-presets', [
            'name' => 'Garansi baterai',
            'days' => 30,
        ])->assertForbidden();

        $presetResponse = $this->actingAs($owner)->postJson('/api/owner/warranty-presets', [
            'name' => 'Garansi baterai',
            'days' => 30,
        ])->assertCreated();
        $presetId = $presetResponse->json('data.id');

        $this->actingAs($technician)->getJson('/api/warranty-presets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $presetId)
            ->assertJsonPath('data.0.days', 30);

        $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Preset',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'Laptop Preset',
            'keluhan' => 'Baterai cepat habis',
            'teknisi_id' => $technician->id,
            'warranty_days' => 365,
        ])->assertUnprocessable();

        $serviceResponse = $this->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Preset',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'Laptop Preset',
            'keluhan' => 'Baterai cepat habis',
            'teknisi_id' => $technician->id,
            'warranty_preset_id' => $presetId,
        ])->assertOk();
        $service = Service::findOrFail($serviceResponse->json('service_id'));
        $this->assertSame($presetId, $service->warranty_preset_id);
        $this->assertSame(30, $service->warranty_days);

        $this->actingAs($technician)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'diperiksa',
            'warranty_days' => 365,
        ])->assertUnprocessable();

        $this->actingAs($owner)->putJson('/api/owner/warranty-presets/'.$presetId, [
            'name' => 'Garansi baterai',
            'days' => 90,
        ])->assertOk();
        $this->assertSame(30, $service->fresh()->warranty_days);
        $this->assertSame(90, WarrantyPreset::findOrFail($presetId)->days);

        $this->deleteJson('/api/owner/warranty-presets/'.$presetId)
            ->assertOk()
            ->assertJsonPath('message', 'Preset dinonaktifkan karena sudah dipakai servis.');
        $this->assertDatabaseHas('warranty_presets', ['id' => $presetId, 'active' => false]);
        $this->assertSame(30, $service->fresh()->warranty_days);
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

        $uploadResponse = $this->actingAs($admin)->post('/api/admin/service/photos', [
            'service_id' => $service->id,
            'kind' => 'intake',
            'photos' => [UploadedFile::fake()->image('unit.png', 640, 480)->size(500)],
        ])->assertCreated()
            ->assertJsonPath('data.0.kind', 'intake');

        $photo = $service->photos()->sole();
        Storage::disk('service_photos')->assertExists($photo->path);
        Storage::disk('service_photos')->assertExists($photo->thumbnail_path);
        Storage::disk('public')->assertMissing($photo->path);
        Storage::disk('public')->assertMissing($photo->thumbnail_path);
        $tracking = $this->getJson('/api/tracking-link/'.$service->tracking_key)
            ->assertOk()
            ->assertJsonPath('data.photos.0.kind', 'intake')
            ->assertJsonPath('data.timeline.0.catatan', 'Foto kondisi saat penerimaan ditambahkan.');
        $this->actingAs($technician)->getJson('/api/admin/nota/'.$service->no_nota)
            ->assertOk()
            ->assertJsonPath('data.photos.0.kind', 'intake')
            ->assertJsonPath('data.photos.0.thumbnail_url', $uploadResponse->json('data.0.thumbnail_url'));
        $this->actingAs($technician)->get('/teknisi')
            ->assertOk()
            ->assertSee('Foto kondisi servis')
            ->assertSee('+ Tambah foto')
            ->assertSee('Sebelum servis')
            ->assertSee('Sesudah servis');
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

        $workloads = $this->getJson('/api/admin/teknisi')->assertOk()->json('data');
        $assignedTechnicianWorkload = collect($workloads)->firstWhere('id', $technician->id);
        $this->assertSame(1, $assignedTechnicianWorkload['beban_kerja']);
        $this->actingAs($otherTechnician)->getJson('/api/admin/services-active')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($technician)->getJson('/api/admin/services-active')
            ->assertOk()->assertJsonPath('data.0.priority', 'urgent');

        $service = Service::findOrFail($created->json('service_id'));
        $this->actingAs($admin)->get('/api/admin/receipts/'.$service->id.'/intake?paper=58')
            ->assertOk()->assertSee($service->tracking_key)->assertSee('Thermal 58 mm');
    }

    public function test_technician_job_list_counts_filters_and_orders_assigned_work_by_deadline_then_priority(): void
    {
        $technician = $this->user('teknisi', 'teknisi_job_list');
        $this->service([
            'teknisi_id' => $technician->id,
            'status' => 'dikerjakan',
            'priority' => 'normal',
            'estimated_completion_at' => today()->addDays(2),
        ]);
        $urgent = $this->service([
            'teknisi_id' => $technician->id,
            'status' => 'masuk',
            'priority' => 'urgent',
            'estimated_completion_at' => today()->addDays(2),
        ]);
        $this->service([
            'teknisi_id' => $technician->id,
            'status' => 'dikerjakan',
            'priority' => 'high',
            'estimated_completion_at' => today()->addDays(5),
        ]);
        $this->service([
            'teknisi_id' => $technician->id,
            'status' => 'selesai',
            'estimated_completion_at' => today()->addDay(),
        ]);
        $this->service(['status' => 'masuk']);

        $response = $this->actingAs($technician)->getJson('/api/admin/services-active')->assertOk();
        $response->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $urgent->id)
            ->assertJsonPath('data.0.keluhan', 'Tidak menyala')
            ->assertJsonPath('meta.work_counts.all', 4)
            ->assertJsonPath('meta.work_counts.not_started', 1)
            ->assertJsonPath('meta.work_counts.in_progress', 2)
            ->assertJsonPath('meta.work_counts.completed', 1);

        $this->getJson('/api/admin/services-active?work_filter=not_started')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $urgent->id);
        $this->getJson('/api/admin/services-active?work_filter=in_progress')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/services-active?work_filter=completed')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_service_estimate_uses_median_history_queue_and_records_actual_completion(): void
    {
        $admin = $this->user('admin', 'admin_scheduling');
        $technician = $this->user('teknisi', 'teknisi_scheduling');
        foreach ([4, 6, 8] as $duration) {
            $historical = $this->service(['jenis_unit' => 'Laptop', 'status' => 'selesai']);
            $historical->forceFill([
                'created_at' => today()->subDays(30),
                'actual_completed_at' => today()->subDays(30 - $duration)->setTime(12, 0),
            ])->save();
        }
        $this->service([
            'teknisi_id' => $technician->id,
            'status' => 'dikerjakan',
            'jenis_unit' => 'Laptop',
            'estimated_completion_at' => today()->addDays(2),
        ]);

        $this->actingAs($admin)->getJson('/api/admin/teknisi?jenis_unit=Laptop')
            ->assertOk()
            ->assertJsonPath('data.0.durasi_estimasi_hari', 6)
            ->assertJsonPath('data.0.sumber_durasi', 'historis_jenis_unit')
            ->assertJsonPath('data.0.antrean_hari', 3)
            ->assertJsonPath('data.0.rekomendasi_selesai', today()->addDays(9)->toDateString());

        $created = $this->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Baru',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'Laptop Baru',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
        ])->assertOk()
            ->assertJsonPath('estimated_completion_at', today()->addDays(9)->toDateString())
            ->assertJsonPath('initial_estimated_completion_at', today()->addDays(9)->toDateString());

        $service = Service::findOrFail($created->json('service_id'));
        $this->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'selesai',
            'catatan_teknis' => 'Perbaikan selesai dan sudah diuji.',
        ])->assertOk();
        $this->assertNotNull($service->fresh()->actual_completed_at);
        $this->assertSame(
            today()->addDays(9)->toDateString(),
            $service->fresh()->initial_estimated_completion_at->toDateString(),
        );
    }

    public function test_service_estimate_uses_three_calendar_day_fallback_without_history(): void
    {
        $admin = $this->user('admin', 'admin_fallback_schedule');
        $created = $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Estimasi',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Lainnya',
            'merek_tipe' => 'Perangkat Baru',
            'keluhan' => 'Tidak berfungsi',
        ])->assertOk()
            ->assertJsonPath('estimated_completion_at', today()->addDays(3)->toDateString())
            ->assertJsonPath('initial_estimated_completion_at', today()->addDays(3)->toDateString());
    }
}
