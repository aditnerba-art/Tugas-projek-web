<?php

namespace Tests;

use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeWhatsAppGateway;

abstract class TestCase extends BaseTestCase
{
    protected FakeWhatsAppGateway $whatsAppGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whatsAppGateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->whatsAppGateway);
    }
}
