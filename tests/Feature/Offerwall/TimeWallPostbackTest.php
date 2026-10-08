<?php

namespace Tests\Feature\Offerwall;

use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TimeWall postback intake — verified 2026-10-07 from the owner's
 * site-owner dashboard: GET params, {hash} = SHA256(userID . revenue .
 * SecretKey) over the RAW query-string values, server IP whitelist,
 * currency/revenue coin translation, and non-earning type guard.
 */
class TimeWallPostbackTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOfferwalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesOfferwalls();
    }

    protected function enableTimeWall(array $overrides = []): OfferwallProvider
    {
        $provider = OfferwallProvider::where('slug', 'timewall')->firstOrFail();
        $provider->update(array_merge([
            'enabled' => true,
            // Stands in for the Secret Key from the TimeWall dashboard.
            'postback_secret' => 'tw-secret-key-123',
            // Most tests run from 127.0.0.1; whitelist tests set this.
            'ip_whitelist' => null,
        ], $overrides));

        return $provider->fresh();
    }

    /**
     * Build TimeWall's real GET params. The hash is computed exactly the
     * way TimeWall documents: SHA256 hex of (userid . revenue . secret)
     * with the revenue string verbatim.
     */
    protected function twParams(OfferwallProvider $provider, User $user, array $overrides = []): array
    {
        $params = array_merge([
            'userid' => (string) $user->id,
            'txid' => 'TW-1',
            'revenue' => '0.10',
            'currency' => '10',
            'ip' => '203.0.113.5',
            'type' => 'offer',
        ], $overrides);

        // When the caller overrides revenue explicitly AND wants the hash
        // to match, they pass 'hash' themselves; otherwise compute it.
        if (! array_key_exists('hash', $overrides)) {
            $params['hash'] = hash(
                'sha256',
                $params['userid'] . $params['revenue'] . $provider->postback_secret
            );
        }

        return $params;
    }

    protected function twUrl(array $params): string
    {
        return '/postback/timewall?' . http_build_query($params);
    }

    public function test_valid_hash_credits_currency_coins(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $response = $this->getJson($this->twUrl($this->twParams($provider, $user)));

        $response->assertOk();
        $this->assertSame('credited', $response->json('status'));
        // currency=10 coins, 70% revenue share -> 7 coins to the user.
        $this->assertSame(7, $response->json('user_coins'));

        $conversion = OfferwallConversion::where('provider_tx_id', 'TW-1')->firstOrFail();
        $this->assertSame($user->id, $conversion->user_id);
        $this->assertSame(10, $conversion->payout_coins);
        $this->assertSame('credited', $conversion->status);
    }

    public function test_tampered_revenue_is_rejected(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // Hash was computed for revenue "0.10" but "9.99" is sent.
        $params = $this->twParams($provider, $user, ['txid' => 'TW-2']);
        $params['revenue'] = '9.99';

        $response = $this->getJson($this->twUrl($params));

        $response->assertStatus(401);
        $this->assertSame('invalid_signature', $response->json('status'));
        $this->assertDatabaseMissing('offerwall_conversions', ['provider_tx_id' => 'TW-2']);
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $params = $this->twParams($provider, $user, ['txid' => 'TW-3', 'hash' => 'deadbeef']);

        $response = $this->getJson($this->twUrl($params));

        $response->assertStatus(401);
        $this->assertSame('invalid_signature', $response->json('status'));
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_revenue_string_mismatch_is_rejected(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // TimeWall hashes the revenue EXACTLY as sent: "0.5" hashes
        // differently from "0.50". A reformatted value must fail.
        $params = $this->twParams($provider, $user, [
            'txid' => 'TW-4',
            'revenue' => '0.5',
        ]);
        $params['revenue'] = '0.50'; // reformat after the hash was computed

        $response = $this->getJson($this->twUrl($params));

        $response->assertStatus(401);
        $this->assertSame('invalid_signature', $response->json('status'));
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_missing_hash_is_rejected(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $params = $this->twParams($provider, $user, ['txid' => 'TW-5']);
        unset($params['hash']);

        $response = $this->getJson($this->twUrl($params));

        $response->assertStatus(401);
        $this->assertSame('invalid_signature', $response->json('status'));
    }

    public function test_non_whitelisted_ip_is_rejected(): void
    {
        $provider = $this->enableTimeWall([
            'ip_whitelist' => "18.156.132.55\n51.81.120.73\n142.111.248.18",
        ]);
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // Signature is valid; the rejection must come from the IP check.
        $response = $this->getJson($this->twUrl($this->twParams($provider, $user, ['txid' => 'TW-6'])));

        $response->assertStatus(403);
        $this->assertSame('ip_blocked', $response->json('status'));
        $this->assertDatabaseMissing('offerwall_conversions', ['provider_tx_id' => 'TW-6']);
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_whitelisted_ip_is_allowed(): void
    {
        $provider = $this->enableTimeWall([
            'ip_whitelist' => "18.156.132.55\n51.81.120.73\n142.111.248.18",
        ]);
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $response = $this->call(
            'GET',
            $this->twUrl($this->twParams($provider, $user, ['txid' => 'TW-7'])),
            [],
            [],
            [],
            ['REMOTE_ADDR' => '18.156.132.55']
        );

        $response->assertOk();
        $this->assertSame('credited', $response->json('status'));
        $this->assertSame(7, $response->json('user_coins'));
    }

    public function test_withdrawal_type_is_never_credited(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $before = $user->fresh()->coinBalance();

        // Valid signature, but a non-earning event type.
        $response = $this->getJson($this->twUrl($this->twParams($provider, $user, [
            'txid' => 'TW-8',
            'type' => 'withdraw',
            'withdrawid' => '6571861',
        ])));

        $response->assertOk();
        $this->assertSame('chargeback', $response->json('status'));
        $this->assertSame($before, $user->fresh()->coinBalance());

        // Logged for admin review, never credited.
        $conversion = OfferwallConversion::where('provider_tx_id', 'TW-8:chargeback')->firstOrFail();
        $this->assertSame(0, $conversion->user_coins);
    }

    public function test_currency_falls_back_to_revenue_times_rate(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        // No currency param: payout = revenue (0.25) x rate (5000) = 1250.
        $params = $this->twParams($provider, $user, ['txid' => 'TW-9', 'revenue' => '0.25']);
        unset($params['currency']);
        // Recompute the hash without the currency param (hash only covers
        // userid + revenue + secret anyway).
        $params['hash'] = hash(
            'sha256',
            $params['userid'] . $params['revenue'] . $provider->postback_secret
        );

        $response = $this->getJson($this->twUrl($params));

        $response->assertOk();
        $this->assertSame('credited', $response->json('status'));
        // 1250 coins x 70% = 875.
        $this->assertSame(875, $response->json('user_coins'));

        $conversion = OfferwallConversion::where('provider_tx_id', 'TW-9')->firstOrFail();
        $this->assertSame(1250, $conversion->payout_coins);
    }

    public function test_zero_payout_is_rejected_not_credited(): void
    {
        $provider = $this->enableTimeWall();
        $user = $this->makeUser();
        $this->makeClick($provider, $user);

        $params = $this->twParams($provider, $user, ['txid' => 'TW-10', 'revenue' => '0.00']);
        unset($params['currency']);

        $response = $this->getJson($this->twUrl($params));

        $response->assertOk();
        $this->assertSame('rejected', $response->json('status'));
        $this->assertSame(0, $user->fresh()->coinBalance());
    }
}
