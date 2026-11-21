<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedProxyConfigurationTest extends TestCase
{
    public function test_only_configured_proxies_can_assert_https_forwarded_proto(): void
    {
        config(['app.trusted_proxies' => '10.0.0.0/8']);

        $trustedRequest = Request::create('http://internal.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $trustedResponse = (new TrustProxies)->handle(
            $trustedRequest,
            fn (Request $request) => response()->json(['secure' => $request->isSecure()])
        );
        $this->assertTrue($trustedResponse->getData()->secure);

        $untrustedRequest = Request::create('http://internal.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $untrustedResponse = (new TrustProxies)->handle(
            $untrustedRequest,
            fn (Request $request) => response()->json(['secure' => $request->isSecure()])
        );
        $this->assertFalse($untrustedResponse->getData()->secure);
    }
}
