<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Backup gagal',
            'message' => $this->message,
            'kind' => 'database_backup_failed',
        ];
    }
}
