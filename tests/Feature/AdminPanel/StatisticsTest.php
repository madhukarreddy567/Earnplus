<?php

namespace Tests\Feature\AdminPanel;

use App\Models\AdImpression;
use App\Models\AdNetwork;
use App\Models\AdPlacement;
use App\Models\CoinTransaction;
use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: the admin dashboard statistics are computed from live data.
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);
    }

    protected function makeUser(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        Wallet::create(['user_id' => $user->id, 'coins' => 0, 'lifetime_earned' => 0]);

        return $user;
    }

    /** @test */
    public function dashboard_shows_user_statistics(): void
    {
        $referrer = $this->makeUser(['created_at' => now()->subDays(2)]);
        $this->makeUser([
            'created_at' => now()->subDays(2),
            'referred_by' => $referrer->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Admin dashboard');
        $response->assertSee('Pending withdrawals');
        $response->assertSee('Registrations — last 30 days');
    }

    /** @test */
    public function coin_stats_group_credits_and_debits_by_source(): void
    {
        $user = $this->makeUser();

        CoinTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => 50,
            'source' => 'signup_bonus', 'balance_after' => 50,
        ]);
        CoinTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => 10,
            'source' => 'spin', 'balance_after' => 60,
        ]);
        CoinTransaction::create([
            'user_id' => $user->id, 'type' => 'debit', 'amount' => 20,
            'source' => 'withdrawal', 'balance_after' => 40,
        ]);

        $user->wallet()->update(['coins' => 40, 'lifetime_earned' => 60]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Coin economy by source');
        $response->assertSee('40'); // outstanding
    }

    /** @test */
    public function pending_withdrawal_widget_links_to_review_pages(): void
    {
        $user = $this->makeUser();
        $method = WithdrawalMethod::create([
            'type' => WithdrawalMethod::TYPE_UPI,
            'name' => 'UPI',
            'enabled' => true,
            'min_amount' => 1000,
            'max_amount' => 1000000,
            'currency' => 'INR',
            'config' => null,
            'sort_order' => 1,
        ]);

        $w = Withdrawal::create([
            'user_id' => $user->id,
            'method_id' => $method->id,
            'coins_debited' => 1000,
            'amount_paise' => 1000,
            'tax_paise' => 0,
            'net_paise' => 1000,
            'currency' => 'INR',
            'details' => ['upi_id' => 'test@upi'],
            'status' => Withdrawal::STATUS_PENDING,
            'idempotency_key' => 'test-wd-' . uniqid(),
            'requested_at' => now(),
        ]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Pending withdrawals (1)');
        $response->assertSee(route('admin.withdrawals.show', $w), false);
    }

    /** @test */
    public function revenue_stats_include_offerwall_earnings_by_provider(): void
    {
        $provider = OfferwallProvider::create([
            'name' => 'Demo', 'slug' => 'demo', 'enabled' => true,
            'postback_secret' => 'secret', 'config' => [],
        ]);
        $user = $this->makeUser();

        OfferwallConversion::create([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'provider_tx_id' => 'tx-1',
            'payout_coins' => 200,
            'user_coins' => 200,
            'status' => OfferwallConversion::STATUS_CREDITED,
            'credited_at' => now(),
        ]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Revenue');
        $response->assertSee('Demo');
    }

    /** @test */
    public function impression_stats_group_by_network_and_device(): void
    {
        $network = AdNetwork::create([
            'name' => 'TestNet', 'slug' => 'testnet-' . uniqid(), 'enabled' => true,
            'type' => AdNetwork::TYPE_CUSTOM, 'config' => [],
        ]);
        $placement = AdPlacement::create([
            'network_id' => $network->id,
            'name' => 'Banner', 'slug' => 'banner-' . uniqid(),
            'slot' => 'dashboard-banner', 'enabled' => true,
            'placement_type' => AdPlacement::TYPE_BANNER,
            'device' => AdPlacement::DEVICE_ALL, 'pages' => null,
            'frequency_cap_per_session' => 3, 'priority' => 10, 'coins' => 0,
            'custom_code' => '<div>TEST</div>',
        ]);

        AdImpression::create([
            'placement_id' => $placement->id, 'ip' => '127.0.0.1',
            'device' => 'android', 'page' => 'dashboard', 'rewarded' => false,
        ]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('TestNet');
        $response->assertSee('android');
    }

    /** @test */
    public function guests_cannot_see_the_dashboard(): void
    {
        $this->post('/admin/logout');

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
