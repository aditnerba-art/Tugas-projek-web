<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceRate;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $rate = ServiceRate::create(['name' => 'Servis Umum', 'amount' => 150000, 'active' => true]);
        ServiceAccessToken::create([
            'service_id' => $service->id,
            'token_hash' => hash_hmac('sha256', '54321', (string) config('app.key')),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)->putJson('/api/admin/service/status', [
            'service_id' => $service->id,
            'status_baru' => 'menunggu_persetujuan',
            'service_rate_id' => $rate->id,
        ])->assertOk();
        $this->assertDatabaseHas('notification_logs', [
            'service_id' => $service->id,
            'message_type' => 'cost_approval',
            'status' => 'sent',
        ]);

        auth()->logout();
        $this->postJson('/api/customer/login', [
            'kode_akses' => '54321',
            'customer_name' => $customer->nama,
        ])->assertOk();
        $this->postJson('/api/customer/approval', [
            'service_id' => $service->id,
            'status_baru' => 'dikerjakan',
        ])->assertOk();

        $this->actingAs($admin)->putJson('/api/admin/service/status', [
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
        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'Asia/Jakarta'));

        try {
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
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pickup_reminders_are_limited_to_three_and_spaced_at_least_three_days_apart(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'Asia/Jakarta'));

        try {
            $customer = Customer::create(['nama' => 'Pelanggan Tes', 'no_wa' => '08123456789']);
            $service = Service::create([
                'no_nota' => 'SRV-WA-REMIND-LIMIT',
                'customer_id' => $customer->id,
                'merek_tipe' => 'Laptop',
                'keluhan' => 'Rusak',
                'status' => 'selesai',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ]);
            $recentlyReminded = Service::create([
                'no_nota' => 'SRV-WA-REMIND-GAP',
                'customer_id' => $customer->id,
                'merek_tipe' => 'Tablet',
                'keluhan' => 'Rusak',
                'status' => 'selesai',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ]);
            DB::table('services')->whereIn('id', [$service->id, $recentlyReminded->id])
                ->update(['updated_at' => now()->subDays(10)]);

            foreach ([10, 6] as $daysAgo) {
                $log = NotificationLog::create([
                    'service_id' => $service->id,
                    'message_type' => 'pickup_reminder',
                    'recipient' => '628123456789',
                    'message' => 'Pengingat',
                    'status' => 'sent',
                ]);
                DB::table('notification_logs')->where('id', $log->id)->update([
                    'created_at' => now()->subDays($daysAgo),
                    'updated_at' => now()->subDays($daysAgo),
                ]);
            }
            $recentReminder = NotificationLog::create([
                'service_id' => $recentlyReminded->id,
                'message_type' => 'pickup_reminder',
                'recipient' => '628123456789',
                'message' => 'Pengingat',
                'status' => 'sent',
            ]);
            DB::table('notification_logs')->where('id', $recentReminder->id)->update([
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);

            $notifications = app(WhatsAppNotificationService::class);
            $this->assertSame(1, $notifications->queuePickupReminders());
            $this->assertSame(3, NotificationLog::where('service_id', $service->id)->where('message_type', 'pickup_reminder')->count());
            $this->assertSame(1, NotificationLog::where('service_id', $recentlyReminded->id)->where('message_type', 'pickup_reminder')->count());
            $this->assertSame(0, $notifications->queuePickupReminders());

            Carbon::setTestNow(now()->addDay());
            $this->assertSame(1, $notifications->queuePickupReminders());
            $this->assertCount(2, $this->whatsAppGateway->messages);
        } finally {
            Carbon::setTestNow();
        }
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
