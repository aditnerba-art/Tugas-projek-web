<?php

use App\Models\CustomerSession;
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

Broadcast::channel('customer-status.{channelKey}', function ($user, string $channelKey): bool {
    $sessionHash = request()->session()->get('customer_session_hash');
    if (! $sessionHash) {
        return false;
    }

    $customerSession = CustomerSession::with('accessToken.service')
        ->where('session_hash', $sessionHash)
        ->where('expires_at', '>', now())
        ->first();

    if (! $customerSession
        || $customerSession->accessToken->revoked_at
        || ! $customerSession->accessToken->expires_at->isFuture()
        || (int) request()->session()->get('customer_service_id') !== $customerSession->accessToken->service_id) {
        return false;
    }

    $currentChannelKey = $customerSession->accessToken->service->customerStatusChannelKey();

    return $currentChannelKey !== null && hash_equals($currentChannelKey, $channelKey);
});
