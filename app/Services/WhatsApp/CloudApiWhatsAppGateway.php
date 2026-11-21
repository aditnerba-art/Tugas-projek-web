<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $recipient, string $message): void
    {
        $token = (string) config('services.whatsapp.cloud.token');
        $phoneNumberId = (string) config('services.whatsapp.cloud.phone_number_id');
        if ($token === '' || $phoneNumberId === '') {
            throw new RuntimeException('Token dan phone number ID WhatsApp Cloud API wajib dikonfigurasi.');
        }

        Http::acceptJson()
            ->withToken($token)
            ->post(rtrim((string) config('services.whatsapp.cloud.url'), '/').'/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => ['body' => $message],
            ])
            ->throw();
    }
}
