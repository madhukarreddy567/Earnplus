<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickTrackingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_click_is_recorded_and_redirects_to_provider(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->get('/tasks/out/' . $provider->slug);

        $response->assertRedirect();
        $click = OfferwallClick::first();
        $this->assertNotNull($click);
        $this->assertSame($provider->id, $click->provider_id);
        $this->assertSame($user->id, $click->user_id);
        $this->assertSame(OfferwallClick::STATUS_CLICKED, $click->status);
        $this->assertTrue($click->expires_at->isFuture());
        $this->assertNotNull($click->device_fingerprint);
        $this->assertStringContainsString($click->click_uid, $response->headers->get('Location'));
    }

    public function test_click_uid_and_user_id_placeholders_are_substituted(): void
    {
        $provider = $this->makeProvider([
            'config' => ['offer_url_template' => 'https://example.com/w?uid={click_uid}&u={user_id}'],
        ]);
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->get('/tasks/out/' . $provider->slug);

        $location = $response->headers->get('Location');
        $click = OfferwallClick::first();
        $this->assertStringContainsString('uid=' . $click->click_uid, $location);
        $this->assertStringContainsString('u=' . $user->id, $location);
    }

    public function test_repeat_click_reuses_active_click(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get('/tasks/out/' . $provider->slug);
        $this->get('/tasks/out/' . $provider->slug);

        $this->assertSame(1, OfferwallClick::count());
    }

    public function test_disabled_provider_returns_404(): void
    {
        $provider = $this->makeProvider(['enabled' => false]);
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get('/tasks/out/' . $provider->slug)->assertNotFound();
    }

    public function test_unknown_provider_slug_returns_404(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get('/tasks/out/no-such-provider')->assertNotFound();
    }

    public function test_click_requires_login(): void
    {
        $provider = $this->makeProvider();

        $this->get('/tasks/out/' . $provider->slug)->assertRedirect('/login');
    }
}
