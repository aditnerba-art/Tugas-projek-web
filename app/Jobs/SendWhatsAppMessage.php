<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $notificationLogId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(WhatsAppGateway $gateway): void
    {
        $log = NotificationLog::findOrFail($this->notificationLogId);
        $gateway->send($log->recipient, $log->message);

        $log->update([
            'status' => 'sent',
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationLog::whereKey($this->notificationLogId)->update([
            'status' => 'failed',
            'error' => $exception?->getMessage() ?? 'Pengiriman gagal tanpa keterangan.',
        ]);
    }
}
