<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('script-src', $csp);
        // No external script sources and no unsafe-inline for scripts.
        $this->assertStringNotContainsString('https://', explode(';', $csp)[1]);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', $csp)[1]);
    }

    public function test_csp_carries_a_nonce_and_inline_scripts_use_it(): void
    {
        $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $response = $this->actingAs($user)->get('/spin');
        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $csp, $m);
        $this->assertNotEmpty($m, 'CSP header must contain a nonce');

        // Every inline script on the page must carry the same nonce.
        preg_match_all('/<script(?![^>]*src=)[^>]*>/', $response->getContent(), $tags);
        $this->assertNotEmpty($tags[0], 'expected inline scripts on the spin page');
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $tag, "inline script missing nonce: {$tag}");
        }
    }

    public function test_hsts_is_off_by_default_and_on_when_enabled(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        Setting::set('hsts_enabled', '1', 'security');

        $this->get('/')->assertHeader('Strict-Transport-Security');
    }

    public function test_nonce_is_unique_per_request(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        preg_match("/'nonce-([^']+)'/", $first, $a);
        preg_match("/'nonce-([^']+)'/", $second, $b);

        $this->assertNotEmpty($a[1]);
        $this->assertNotEmpty($b[1]);
        $this->assertNotSame($a[1], $b[1]);
    }
}
