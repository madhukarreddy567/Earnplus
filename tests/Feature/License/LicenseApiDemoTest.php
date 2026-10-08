<?php

namespace Tests\Feature\License;

use App\Models\OfferwallProvider;
use App\Models\Setting;
use App\Services\LicenseService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Offerwall\CreatesOfferwalls;
use Tests\TestCase;

/**
 * Phase 13: the demo sandbox must stay hidden from the mobile API in
 * production mode, exactly like the web tasks page.
 */
class LicenseApiDemoTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    protected function enableProduction(): void
    {
        Setting::set('app_mode', 'production', 'license');
        Setting::set('licensed_domain', 'example.com', 'license');
        Setting::set('license_signature', LicenseService::SIGNATURE, 'license');
    }

    protected function apiUser(): void
    {
        Sanctum::actingAs($this->makeUser());
    }

    protected function demoProvider(): OfferwallProvider
    {
        return OfferwallProvider::where('slug', 'demo')->firstOrFail();
    }

    public function test_api_lists_demo_provider_in_development(): void
    {
        $this->apiUser();

        $slugs = collect($this->getJson('http://localhost/api/tasks/providers')->json('data'))
            ->pluck('slug');

        $this->assertContains('demo', $slugs);
    }

    public function test_api_hides_demo_provider_in_production(): void
    {
        $this->enableProduction();
        $this->apiUser();
        $this->makeProvider(['slug' => 'real-prod-list']);

        $slugs = collect($this->getJson('http://example.com/api/tasks/providers')->json('data'))
            ->pluck('slug');

        $this->assertNotContains('demo', $slugs);
        // Real providers still listed.
        $this->assertContains('real-prod-list', $slugs);
    }

    public function test_api_click_on_demo_provider_404s_in_production(): void
    {
        $this->enableProduction();
        $this->apiUser();

        $this->postJson('http://example.com/api/tasks/click/' . $this->demoProvider()->slug)
            ->assertNotFound();
    }

    public function test_api_click_on_real_provider_still_works_in_production(): void
    {
        $this->enableProduction();
        $this->apiUser();

        $provider = $this->makeProvider(['slug' => 'real-api-prod']);

        $this->postJson('http://example.com/api/tasks/click/' . $provider->slug)
            ->assertOk()
            ->assertJsonStructure(['url']);
    }
}
