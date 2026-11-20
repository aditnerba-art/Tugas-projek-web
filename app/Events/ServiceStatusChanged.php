<?php

namespace App\Events;

use App\Models\ServiceStatusLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ServiceStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(ServiceStatusLog $statusLog)
    {
        $statusLog->loadMissing(['service', 'user']);
        $this->serviceId = $statusLog->service_id;
        $this->serviceNumber = $statusLog->service->no_nota;
        $this->technicianId = $statusLog->service->teknisi_id;
        $this->customerChannelKey = $statusLog->service->customerStatusChannelKey();
        $this->oldStatus = $statusLog->status_lama;
        $this->newStatus = $statusLog->status_baru;
        $this->note = $statusLog->catatan;
        $this->userName = $statusLog->user?->nama;
        $this->createdAt = $statusLog->created_at?->toIso8601String();
    }

    public int $serviceId;

    public string $serviceNumber;

    public ?int $technicianId;

    public ?string $customerChannelKey;

    public ?string $oldStatus;

    public string $newStatus;

    public ?string $note;

    public ?string $userName;

    public ?string $createdAt;

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('staff.services')];

        if ($this->technicianId) {
            $channels[] = new PrivateChannel('staff.technician.'.$this->technicianId);
        }

        if ($this->customerChannelKey) {
            $channels[] = new PrivateChannel('customer-status.'.$this->customerChannelKey);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'service.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'service_id' => $this->serviceId,
            'no_nota' => $this->serviceNumber,
            'status_lama' => $this->oldStatus,
            'status_baru' => $this->newStatus,
            'catatan' => $this->note,
            'user' => $this->userName,
            'created_at' => $this->createdAt,
        ];
    }

    public function broadcastWhen(): bool
    {
        return config('realtime.driver') === 'reverb';
    }
}
