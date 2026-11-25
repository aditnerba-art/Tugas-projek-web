<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Service;
use Illuminate\Support\Carbon;
use RuntimeException;

class WhatsAppNotificationService
{
    public function queue(Service $service, string $type, array $variables = []): NotificationLog
    {
        $template = MessageTemplate::where('key', $type)->first();
        if (! $template) {
            throw new RuntimeException("Template WhatsApp [{$type}] tidak ditemukan.");
        }

        $customer = $service->customer;
        $values = array_merge([
            'nama' => $customer->nama,
            'kode' => $service->no_nota,
            'status' => str_replace('_', ' ', $service->status),
            'link' => route('tracking.public.page', $service->tracking_key),
            'unit' => trim(($service->jenis_unit ? $service->jenis_unit.' ' : '').$service->merek_tipe),
            'biaya' => 'Rp '.number_format((float) $service->total_biaya, 0, ',', '.'),
            'estimasi_selesai' => $service->estimated_completion_at?->format('d-m-Y') ?? 'Belum ditentukan',
        ], $variables);
        $message = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $match) use ($values): string {
            return (string) ($values[$match[1]] ?? $match[0]);
        }, $template->body);

        $log = NotificationLog::create([
            'service_id' => $service->id,
            'message_type' => $type,
            'recipient' => PhoneNumber::normalize($customer->no_wa),
            'message' => $message,
            'status' => 'pending',
        ]);

        SendWhatsAppMessage::dispatch($log->id);

        return $log;
    }

    public function queuePickupReminders(): int
    {
        $queued = 0;
        $now = now(config('app.timezone'));

        Service::with('customer')
            ->where('status', 'selesai')
            ->where('updated_at', '<=', $now->copy()->subDay())
            ->chunkById(100, function ($services) use (&$queued, $now): void {
                foreach ($services as $service) {
                    $reminders = NotificationLog::where('service_id', $service->id)
                        ->where('message_type', 'pickup_reminder');
                    if ($reminders->count() >= 3) {
                        continue;
                    }

                    $lastReminder = $reminders->max('created_at');
                    if ($lastReminder && Carbon::parse($lastReminder, config('app.timezone'))->gt($now->copy()->subDays(3))) {
                        continue;
                    }

                    $this->queue($service, 'pickup_reminder');
                    $queued++;
                }
            });

        return $queued;
    }
}
