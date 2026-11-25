<?php

use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:remind-uncollected', function () {
    $queued = app(WhatsAppNotificationService::class)->queuePickupReminders();

    $this->info("Pengingat unit belum diambil dimasukkan ke antrean: {$queued}");
})->purpose('Queue daily WhatsApp reminders for completed units not yet collected');

Schedule::command('notifications:remind-uncollected')
    ->dailyAt('09:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
Schedule::command('database:backup --verify')
    ->dailyAt('02:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
