<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('staff.services', function (User $user): bool {
    return in_array($user->role, ['admin', 'owner'], true);
});

Broadcast::channel('staff.technician.{userId}', function (User $user, int $userId): bool {
    return $user->role === 'teknisi' && $user->id === $userId;
});

