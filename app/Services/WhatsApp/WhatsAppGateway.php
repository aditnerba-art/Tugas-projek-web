<?php

namespace App\Services\WhatsApp;

interface WhatsAppGateway
{
    public function send(string $recipient, string $message): void;
}
