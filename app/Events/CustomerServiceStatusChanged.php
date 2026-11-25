<?php

namespace App\Events;

use App\Models\ServiceStatusLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CustomerServiceStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 3;

    public array $backoff = [1, 5, 15];

    public function __construct(ServiceStatusLog $statusLog)
    {
        $statusLog->loadMissing('service');
        $this->channelKey = $statusLog->service->customerStatusChannelKey();
        $this->status = $statusLog->status_baru;
        $this->publicNote = $statusLog->catatan;
        $this->createdAt = $statusLog->created_at?->toIso8601String();
    }

    public ?string $channelKey;

    public string $status;

    public ?string $publicNote;

    public ?string $createdAt;

    public function broadcastOn(): array
    {
        return $this->channelKey
            ? [new Channel('customer-status.'.$this->channelKey)]
            : [];
    }

    public function broadcastAs(): string
    {
        return 'customer.status.changed';
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'waktu' => $this->createdAt,
            'catatan' => $this->publicNote,
        ];
    }

    public function broadcastWhen(): bool
    {
        return config('realtime.driver') === 'reverb';
    }
}
