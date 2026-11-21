<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FonnteWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $recipient, string $message): void
    {
        $token = (string) config('services.whatsapp.fonnte.token');
        if ($token === '') {
            throw new RuntimeException('Fonnte API token belum dikonfigurasi.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => $token])
            ->post((string) config('services.whatsapp.fonnte.url'), [
                'target' => $recipient,
                'message' => $message,
            ])
            ->throw();

        if ($response->json('status') !== true) {
            throw new RuntimeException((string) ($response->json('reason') ?? 'Fonnte menolak pengiriman pesan.'));
        }
    }
}
