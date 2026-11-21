<?php

namespace App\Providers;

use App\Services\WhatsApp\CloudApiWhatsAppGateway;
use App\Services\WhatsApp\FonnteWhatsAppGateway;
use App\Services\WhatsApp\WablasWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, function () {
            return match (config('services.whatsapp.driver', 'fonnte')) {
                'fonnte' => new FonnteWhatsAppGateway,
                'wablas' => new WablasWhatsAppGateway,
                'cloud' => new CloudApiWhatsAppGateway,
                default => throw new InvalidArgumentException('Driver WhatsApp tidak dikenal.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardProductionConfiguration();

        RateLimiter::for('customer-code', function (Request $request) {
            $code = (string) $request->input(
                'kode_akses',
                $request->input('token', $request->query('kode_akses', $request->route('identifier', '')))
            );
            $response = fn (Request $request, array $headers) => $request->expectsJson() || $request->is('api/*')
                ? response()->json(['status' => 'error', 'message' => 'Terlalu banyak percobaan kode akses. Coba kembali dalam 10 menit.'], 429, $headers)
                : response()->view('errors.429', [], 429, $headers);

            return [
                Limit::perMinutes(10, 5)->by('customer-ip:'.$request->ip())->response($response),
                Limit::perMinutes(10, 5)->by('customer-code:'.hash('sha256', $code))->response($response),
            ];
        });

        RateLimiter::for('staff-login', function (Request $request) {
            $username = mb_strtolower(trim((string) $request->input('username', '')), 'UTF-8');
            $key = hash('sha256', $username.'|'.$request->ip());

            return Limit::perMinute(5)->by('staff-login:'.$key)->response(
                fn (Request $request, array $headers) => $request->expectsJson() || $request->is('api/*')
                    ? response()->json(['status' => 'error', 'message' => 'Terlalu banyak percobaan login. Coba kembali dalam satu menit.'], 429, $headers)
                    : response()->view('errors.429', [], 429, $headers)
            );
        });
    }

    private function guardProductionConfiguration(): void
    {
        if (config('app.env') !== 'production') {
            return;
        }

        $problems = [];
        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG must be false';
        }

        $placeholders = ['local', 'local-secret', 'change_me'];
        foreach ([
            'REVERB_APP_KEY' => config('broadcasting.connections.reverb.key'),
            'REVERB_APP_SECRET' => config('broadcasting.connections.reverb.secret'),
        ] as $name => $value) {
            if ($value === null || trim((string) $value) === '' || in_array(strtolower(trim((string) $value)), $placeholders, true)) {
                $problems[] = $name.' must be set to a unique deployment value';
            }
        }

        if ($problems !== []) {
            throw new RuntimeException('Unsafe production configuration: '.implode('; ', $problems).'.');
        }
    }
}
