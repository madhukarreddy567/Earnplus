<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallProvider;
use App\Models\User;
use App\Services\OfferwallService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\OfferwallSeeder;
use Database\Seeders\SettingSeeder;

/**
 * Shared builders for offerwall tests.
 */
trait CreatesOfferwalls
{
    protected OfferwallService $offerwalls;

    protected function setUpCreatesOfferwalls(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(OfferwallSeeder::class);
        $this->offerwalls = app(OfferwallService::class);
    }

    protected function makeProvider(array $overrides = []): OfferwallProvider
    {
        return OfferwallProvider::create(array_merge([
            'name' => 'Test Provider',
            'slug' => 'test-provider',
            'enabled' => true,
            'postback_secret' => 'test-secret-123',
            'user_revenue_share' => 70.00,
            'config' => ['offer_url_template' => 'https://example.com/wall?uid={click_uid}'],
            'sandbox_mode' => false,
        ], $overrides));
    }

    protected function makeUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    protected function sign(OfferwallProvider $provider, string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $provider->postback_secret);
    }

    /**
     * POST a signed JSON postback through the real endpoint.
     */
    protected function signedPost(OfferwallProvider $provider, array $payload, ?string $signature = null)
    {
        $rawBody = json_encode($payload);

        return $this->postJson(
            '/postback/' . $provider->slug,
            $payload,
            ['X-Signature' => $signature ?? $this->sign($provider, $rawBody)]
        );
    }

    protected function makeClick(OfferwallProvider $provider, User $user, array $overrides = [])
    {
        return $this->offerwalls->trackClick(
            $provider,
            $user,
            $overrides['ip'] ?? '127.0.0.1',
            $overrides['user_agent'] ?? 'TestAgent/1.0'
        );
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticatedAs(
            \App\Models\Admin::where('email', 'admin@earnplus.local')->first(),
            'admin'
        );
    }
}
