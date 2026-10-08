<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallConversion;
use App\Services\ClickVelocityExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FraudProtectionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    public function test_click_velocity_cap_blocks_the_21st_click(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        // 20 clicks in the last hour: the first creates one, the rest reuse
        // it — so seed 20 raw rows to simulate real volume.
        for ($i = 0; $i < 20; $i++) {
            \App\Models\OfferwallClick::create([
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'click_uid' => 'uid-' . $i . '-' . \Illuminate\Support\Str::random(20),
                'ip' => '127.0.0.1',
                'device_fingerprint' => 'fp-' . $i,
                'status' => \App\Models\OfferwallClick::STATUS_CONVERTED,
                'expires_at' => now()->addDay(),
            ]);
        }

        $this->expectException(ClickVelocityExceededException::class);
        $this->offerwalls->trackClick($provider, $user, '127.0.0.1', 'TestAgent/1.0');
    }

    public function test_clicks_under_the_cap_are_allowed(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();

        $click = $this->offerwalls->trackClick($provider, $user, '127.0.0.1', 'TestAgent/1.0');

        $this->assertNotNull($click->id);
    }

    public function test_http_click_endpoint_blocks_velocity_abuse(): void
    {
        $provider = $this->makeProvider();
        $user = $this->makeUser();
        $this->actingAs($user);

        for ($i = 0; $i < 20; $i++) {
            \App\Models\OfferwallClick::create([
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'click_uid' => 'http-' . $i . '-' . \Illuminate\Support\Str::random(20),
                'ip' => '127.0.0.1',
                'device_fingerprint' => 'fp-' . $i,
                'status' => \App\Models\OfferwallClick::STATUS_CLICKED,
                'expires_at' => now()->subMinute(),
            ]);
        }

        $response = $this->get('/tasks/out/' . $provider->slug);

        $response->assertRedirect(route('tasks'));
        $response->assertSessionHas('error');
    }

    public function test_shared_device_across_more_than_3_users_is_flagged(): void
    {
        $provider = $this->makeProvider();
        $users = [];

        // Same UA + same IP => same fingerprint for all four users.
        for ($i = 0; $i < 4; $i++) {
            $users[] = $this->makeUser();
        }

        foreach ($users as $u) {
            $this->offerwalls->trackClick($provider, $u, '10.0.0.1', 'SharedAgent/1.0');
        }

        $victim = $users[3];
        $click = \App\Models\OfferwallClick::where('user_id', $victim->id)->first();

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-fraud-1',
            'user_id' => $victim->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('status', 'credited');

        $conversion = OfferwallConversion::where('provider_tx_id', 'tx-fraud-1')->first();
        $this->assertContains('shared_device', $conversion->meta['fraud_flags'] ?? []);
    }

    public function test_device_used_by_3_users_is_not_flagged(): void
    {
        $provider = $this->makeProvider();

        for ($i = 0; $i < 3; $i++) {
            $u = $this->makeUser();
            $this->offerwalls->trackClick($provider, $u, '10.0.0.2', 'SmallAgent/1.0');
        }

        $victim = \App\Models\User::latest()->first();
        $click = \App\Models\OfferwallClick::where('user_id', $victim->id)->first();

        $this->signedPost($provider, [
            'provider_tx_id' => 'tx-fraud-2',
            'user_id' => $victim->id,
            'payout' => 100,
            'click_uid' => $click->click_uid,
        ])->assertJsonPath('status', 'credited');

        $conversion = OfferwallConversion::where('provider_tx_id', 'tx-fraud-2')->first();
        $this->assertEmpty($conversion->meta['fraud_flags'] ?? []);
    }
}
