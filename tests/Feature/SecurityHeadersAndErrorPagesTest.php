<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SecurityHeadersAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_error_pages_are_rendered_in_indonesian(): void
    {
        Route::get('/__test-errors/{status}', function (string $status) {
            if ((int) $status === 500) {
                throw new RuntimeException('Tes halaman kesalahan.');
            }

            abort((int) $status);
        });
        config(['app.debug' => false]);

        foreach ([
            403 => 'Akses Ditolak',
            404 => 'Halaman Tidak Ditemukan',
            419 => 'Sesi Kedaluwarsa',
            429 => 'Terlalu Banyak Percobaan',
            500 => 'Terjadi Kesalahan',
        ] as $status => $title) {
            $this->get('/__test-errors/'.$status)
                ->assertStatus($status)
                ->assertSee($title);
        }
    }

    public function test_security_headers_include_csp_sources_for_fonts_tailwind_and_reverb(): void
    {
        config(['app.debug' => true]);
        $response = $this->get('/up')->assertOk();

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString('https://cdn.tailwindcss.com', $policy);
        $this->assertStringContainsString('https://fonts.googleapis.com', $policy);
        $this->assertStringContainsString('https://fonts.gstatic.com', $policy);
        $this->assertStringContainsString('wss:', $policy);
        $this->assertStringContainsString('http://localhost:5173', $policy);

        config(['app.debug' => false]);
        $productionPolicy = $this->get('/up')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('http://localhost:5173', $productionPolicy);
    }
}
