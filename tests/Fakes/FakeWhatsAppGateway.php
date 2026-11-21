<?php

namespace Tests\Fakes;

use App\Services\WhatsApp\WhatsAppGateway;
use RuntimeException;

class FakeWhatsAppGateway implements WhatsAppGateway
{
    public array $messages = [];

    public ?RuntimeException $failure = null;

    public function send(string $recipient, string $message): void
    {
        if ($this->failure) {
            throw $this->failure;
        }

        $this->messages[] = compact('recipient', 'message');
    }
}
