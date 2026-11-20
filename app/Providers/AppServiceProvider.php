<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
                fn (Request $request, array $headers) => $request->expectsJson()
                    ? response()->json(['status' => 'error', 'message' => 'Terlalu banyak percobaan login. Coba kembali dalam satu menit.'], 429, $headers)
                    : response()->view('errors.429', [], 429, $headers)
            );
        });
    }
}
