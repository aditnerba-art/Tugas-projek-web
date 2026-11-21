<?php

namespace Tests\Feature;

use App\Events\ServiceStatusChanged;
use App\Models\Service;
use App\Models\ServiceStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_follow_the_full_repair_lifecycle_and_broadcast_each_status(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $statuses = [
            'masuk',
            'diperiksa', 'menunggu_persetujuan', 'dikerjakan',
            'menunggu_sparepart', 'dikerjakan', 'selesai',
        ];
        Event::fake([ServiceStatusChanged::class]);

        $this->actingAs($admin);
        foreach ($statuses as $status) {
            $this->putJson('/api/admin/service/status', [
                'service_id' => $service->id,
                'status_baru' => $status,
                'catatan_teknis' => 'Update '.$status,
            ])->assertOk();
        }

        $this->assertSame($statuses, ServiceStatusLog::where('service_id', $service->id)
            ->orderBy('id')->pluck('status_baru')->all());
        $this->assertSame('selesai', $service->fresh()->status);
        Event::assertDispatched(ServiceStatusChanged::class, 7);
        $this->assertSame(2, count($this->whatsAppGateway->messages));
    }

    public function test_each_staff_role_is_forbidden_from_other_roles_panels(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin)->getJson('/api/owner/dashboard')->assertForbidden();
        $this->actingAs($technician)->getJson('/api/admin/customers')->assertForbidden();
        $this->actingAs($technician)->getJson('/api/owner/dashboard')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($owner)->postJson('/api/admin/service', [])->assertForbidden();
    }

    public function test_demo_seeder_populates_each_service_status_and_can_be_repeated(): void
    {
        $this->seed(\Database\Seeders\DemoServiceSeeder::class);
        $this->assertDatabaseCount('services', 8);
        $this->assertEqualsCanonicalizing(
            ['masuk', 'diperiksa', 'menunggu_persetujuan', 'dikerjakan', 'menunggu_sparepart', 'selesai', 'diambil', 'batal'],
            Service::distinct()->orderBy('status')->pluck('status')->all(),
        );

        $this->seed(\Database\Seeders\DemoServiceSeeder::class);
        $this->assertDatabaseCount('services', 8);
        $this->assertSame(8, ServiceStatusLog::count());
    }
}
