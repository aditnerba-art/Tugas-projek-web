<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductionConfigurationGuardTest extends TestCase
{
    #[DataProvider('unsafeProductionConfigurations')]
    public function test_production_boot_rejects_debug_mode_and_placeholder_reverb_credentials(array $overrides): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'broadcasting.connections.reverb.key' => 'deployment-key-123',
            'broadcasting.connections.reverb.secret' => 'deployment-secret-456',
        ]);
        config($overrides);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe production configuration');

        (new AppServiceProvider($this->app))->boot();
    }

    public static function unsafeProductionConfigurations(): array
    {
        return [
            'debug mode' => [['app.debug' => true]],
            'local key' => [['broadcasting.connections.reverb.key' => 'local']],
            'local secret' => [['broadcasting.connections.reverb.secret' => 'local-secret']],
            'placeholder key' => [['broadcasting.connections.reverb.key' => 'change_me']],
            'placeholder secret' => [['broadcasting.connections.reverb.secret' => 'change_me']],
            'missing key' => [['broadcasting.connections.reverb.key' => '']],
            'missing secret' => [['broadcasting.connections.reverb.secret' => '']],
        ];
    }

    public function test_production_boot_accepts_custom_reverb_credentials_when_debug_is_off(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'broadcasting.connections.reverb.key' => 'deployment-key-123',
            'broadcasting.connections.reverb.secret' => 'deployment-secret-456',
        ]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertNotNull(RateLimiter::limiter('customer-code'));
    }
}
