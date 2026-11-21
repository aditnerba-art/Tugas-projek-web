<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Service;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_registration_queues_message_with_access_code_and_normalized_phone(): void
    {
        $admin = User::create([
            'nama' => 'Admin Tes',
            'username' => 'admin_whatsapp',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/service', [
            'nama' => 'Pelanggan Tes',
            'no_wa' => '+62 812-3456-7890',
            'jenis_unit' => 'Laptop',
            'merek_tipe' => 'ThinkPad',
            'keluhan' => 'Tidak menyala',
        ])->assertOk()->assertJsonPath('whatsapp_status', 'sent');

        $log = NotificationLog::sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('6281234567890', $log->recipient);
        $this->assertStringContainsString($response->json('customer_token'), $log->message);
        $this->assertStringContainsString('/lacak', $log->message);
        $this->assertSame($log->recipient, $this->whatsAppGateway->messages[0]['recipient']);
    }

    public function test_cost_approval_and_ready_status_changes_queue_their_templates(): void
    {
        $admin = User::create([
            'nama' => 'Admin Tes',
            'username' => 'admin_status',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $customer = Customer::create(['nama' => 'Pelanggan Tes', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-WA-001',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Rusak',
        ]);

        $this->actingAs($admin)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'menunggu_persetujuan',
            'total_biaya' => 150000,
        ])->assertOk();
        $this->assertDatabaseHas('notification_logs', [
            'service_id' => $service->id,
            'message_type' => 'cost_approval',
            'status' => 'sent',
        ]);

        $this->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'selesai',
        ])->assertOk();
        $this->assertDatabaseHas('notification_logs', [
            'service_id' => $service->id,
            'message_type' => 'service_ready',
            'status' => 'sent',
        ]);
        $this->assertCount(2, $this->whatsAppGateway->messages);
    }

    public function test_exhausted_message_failure_is_recorded_on_the_notification_log(): void
    {
        $log = NotificationLog::create([
            'message_type' => 'service_ready',
            'recipient' => '6281234567890',
            'message' => 'Servis siap diambil',
            'status' => 'pending',
        ]);
        $exception = new RuntimeException('Provider tidak tersedia.');
        $this->whatsAppGateway->failure = $exception;
        $job = new SendWhatsAppMessage($log->id);

        try {
            $job->handle($this->whatsAppGateway);
            $this->fail('The provider failure should be propagated for queue retry.');
        } catch (RuntimeException $error) {
            $job->failed($error);
        }

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'status' => 'failed',
            'error' => 'Provider tidak tersedia.',
        ]);
    }

    public function test_daily_reminder_only_targets_old_completed_units_not_collected(): void
    {
        $customer = Customer::create(['nama' => 'Pelanggan Tes', 'no_wa' => '08123456789']);
        $service = Service::create([
            'no_nota' => 'SRV-WA-REMIND',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Laptop',
            'keluhan' => 'Rusak',
            'status' => 'selesai',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(2),
        ]);
        Service::create([
            'no_nota' => 'SRV-WA-PICKED',
            'customer_id' => $customer->id,
            'merek_tipe' => 'Tablet',
            'keluhan' => 'Rusak',
            'status' => 'diambil',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(2),
        ]);
        DB::table('services')->whereIn('no_nota', ['SRV-WA-REMIND', 'SRV-WA-PICKED'])
            ->update(['updated_at' => now()->subDays(2)]);

        $this->assertSame(1, app(WhatsAppNotificationService::class)->queuePickupReminders());

        $this->assertDatabaseHas('notification_logs', [
            'service_id' => $service->id,
            'message_type' => 'pickup_reminder',
            'status' => 'sent',
        ]);
        $this->assertCount(1, $this->whatsAppGateway->messages);
    }

    public function test_owner_can_edit_templates_and_retry_a_failed_notification(): void
    {
        $owner = User::create([
            'nama' => 'Owner Tes',
            'username' => 'owner_whatsapp',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'must_change_password' => false,
        ]);
        $template = MessageTemplate::where('key', 'service_ready')->firstOrFail();
        $this->actingAs($owner)->get('/owner/notifications')->assertOk();
        $this->putJson('/api/owner/notification-templates/'.$template->id, [
            'body' => 'Hai {nama}, servis {kode} siap.',
        ])->assertOk()->assertJsonPath('data.body', 'Hai {nama}, servis {kode} siap.');

        $log = NotificationLog::create([
            'message_type' => 'service_ready',
            'recipient' => '6281234567890',
            'message' => 'Servis siap diambil',
            'status' => 'failed',
            'error' => 'Provider tidak tersedia.',
        ]);
        $this->postJson('/api/owner/notification-logs/'.$log->id.'/resend')
            ->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('notification_logs', ['id' => $log->id, 'status' => 'sent', 'error' => null]);
        $this->assertCount(1, $this->whatsAppGateway->messages);
    }
}
