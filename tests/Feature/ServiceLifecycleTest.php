<?php

namespace Tests\Feature;

use App\Events\CustomerServiceStatusChanged;
use App\Events\ServiceStatusChanged;
use App\Models\Service;
use App\Models\ServiceStatusLog;
use App\Models\User;
use App\Notifications\ServicePanelNotification;
use Database\Seeders\DemoServiceSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
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
        Event::fake([ServiceStatusChanged::class, CustomerServiceStatusChanged::class]);

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
        Event::assertDispatched(CustomerServiceStatusChanged::class, 6);
        Event::assertDispatched(CustomerServiceStatusChanged::class, fn (CustomerServiceStatusChanged $event): bool => array_keys($event->broadcastWith()) === [
            'status', 'waktu', 'catatan',
        ]);
        $this->assertSame(2, count($this->whatsAppGateway->messages));
    }

    public function test_newly_assigned_technician_gets_database_notification_and_staff_realtime_event(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        Event::fake([ServiceStatusChanged::class]);

        $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Tes',
            'no_wa' => '08123456789',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'ThinkPad',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $technician->id,
            'type' => ServicePanelNotification::class,
        ]);
        Event::assertDispatched(ServiceStatusChanged::class, fn (ServiceStatusChanged $event): bool => $event->technicianId === $technician->id
            && collect($event->broadcastOn())->contains(fn ($channel): bool => $channel->name === 'private-staff.technician.'.$technician->id));
    }

    public function test_broadcast_queue_failure_is_reported_without_throwing(): void
    {
        $service = Service::factory()->create();
        $statusLog = ServiceStatusLog::create([
            'service_id' => $service->id,
            'status_lama' => 'masuk',
            'status_baru' => 'diperiksa',
            'catatan' => 'Pemeriksaan dimulai',
            'created_at' => now(),
        ]);
        $failure = new RuntimeException('Queue tidak tersedia.');
        $queueConnection = Mockery::mock();
        $queueConnection->shouldReceive('pushOn')
            ->once()
            ->with('realtime', Mockery::type(BroadcastEvent::class))
            ->andThrow($failure);
        $queueManager = Mockery::mock(QueueManager::class);
        $queueManager->shouldReceive('connection')->once()->with(null)->andReturn($queueConnection);
        $this->app->instance('queue', $queueManager);
        Exceptions::fake();

        $event = new ServiceStatusChanged($statusLog);

        $this->assertSame('realtime', $event->broadcastQueue());
        $this->assertNull($this->app->make(BroadcastManager::class)->queue($event));
        Exceptions::assertReported(RuntimeException::class);
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
        $this->seed(DemoServiceSeeder::class);
        $this->assertDatabaseCount('services', 8);
        $this->assertEqualsCanonicalizing(
            ['masuk', 'diperiksa', 'menunggu_persetujuan', 'dikerjakan', 'menunggu_sparepart', 'selesai', 'diambil', 'batal'],
            Service::distinct()->orderBy('status')->pluck('status')->all(),
        );

        $this->seed(DemoServiceSeeder::class);
        $this->assertDatabaseCount('services', 8);
        $this->assertSame(8, ServiceStatusLog::count());
    }
}
