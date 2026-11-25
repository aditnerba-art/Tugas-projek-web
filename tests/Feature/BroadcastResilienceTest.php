<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BroadcastResilienceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_reverb_failure_is_reported_without_failing_the_service_status_change(): void
    {
        config([
            'realtime.driver' => 'reverb',
            'broadcasting.default' => 'test_failing',
            'broadcasting.connections.test_failing' => ['driver' => 'test_failing'],
            'queue.default' => 'sync',
        ]);
        $failure = new RuntimeException('Reverb tidak tersedia.');
        $broadcaster = Mockery::mock(Broadcaster::class);
        $broadcaster->shouldReceive('broadcast')->once()->andThrow($failure);
        $broadcastManager = $this->app->make(BroadcastManager::class);
        $broadcastManager->forgetDrivers();
        $broadcastManager->extend('test_failing', fn () => $broadcaster);
        Exceptions::fake();

        $admin = User::factory()->admin()->create();
        $customer = Customer::create(['nama' => 'Pelanggan Tes', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-BROADCAST-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'status' => 'masuk',
        ]);

        $this->actingAs($admin)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'diperiksa',
        ])->assertOk();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'diperiksa',
        ]);
        $this->assertNotEmpty(Exceptions::reported(), 'The failed broadcast should be reported.');
        $this->assertSame(
            RuntimeException::class,
            get_class(Exceptions::reported()[0]),
            json_encode(array_map(fn ($exception) => [get_class($exception), $exception->getMessage()], Exceptions::reported())),
        );
    }
}
