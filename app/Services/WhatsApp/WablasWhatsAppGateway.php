<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WablasWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $recipient, string $message): void
    {
        $token = (string) config('services.whatsapp.wablas.token');
        if ($token === '') {
            throw new RuntimeException('Wablas API token belum dikonfigurasi.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => $token])
            ->post((string) config('services.whatsapp.wablas.url'), [
                'phone' => $recipient,
                'message' => $message,
            ])
            ->throw();

        if ($response->json('status') === false) {
            throw new RuntimeException((string) ($response->json('message') ?? 'Wablas menolak pengiriman pesan.'));
        }
    }
}
