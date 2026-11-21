<?php

namespace App\Http\Controllers;

use App\Models\CustomerSession;
use App\Models\Service;
use App\Models\ServicePhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServicePhotoController extends Controller
{
    public function __invoke(Request $request, ServicePhoto $photo, string $variant = 'original'): StreamedResponse
    {
        abort_unless(in_array($variant, ['original', 'thumbnail'], true), 404);

        $service = $photo->service;
        $user = $request->user();
        $trackingKey = $request->query('tracking_key');
        $staffAuthorized = $user instanceof User && $this->staffCanView($user, $service);
        $customerSessionAuthorized = $this->customerSessionCanView($request, $service);
        $trackingKeyAuthorized = is_string($trackingKey)
            && hash_equals((string) $service->tracking_key, $trackingKey);

        abort_unless($staffAuthorized || $customerSessionAuthorized || $trackingKeyAuthorized, 404);

        $path = $variant === 'thumbnail' ? $photo->thumbnail_path : $photo->path;
        $disk = Storage::disk('service_photos');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function staffCanView(User $user, Service $service): bool
    {
        if ($user->must_change_password) {
            return false;
        }

        return in_array($user->role, ['admin', 'owner'], true)
            || ($user->role === 'teknisi' && (int) $service->teknisi_id === (int) $user->id);
    }

    private function customerSessionCanView(Request $request, Service $service): bool
    {
        $sessionHash = $request->session()->get('customer_session_hash');
        if (
            (int) $request->session()->get('customer_service_id') !== (int) $service->id
            || ! is_string($sessionHash)
            || $sessionHash === ''
        ) {
            return false;
        }

        $now = now(config('app.timezone'));

        return CustomerSession::query()
            ->where('session_hash', $sessionHash)
            ->where('expires_at', '>', $now)
            ->whereHas('accessToken', fn ($query) => $query
                ->where('service_id', $service->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now))
            ->exists();
    }
}
