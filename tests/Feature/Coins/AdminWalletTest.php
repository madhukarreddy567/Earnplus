<?php

namespace Tests\Feature\Coins;

use App\Models\Admin;
use App\Models\CoinTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\CoinService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(AdminSeeder::class);
    }

    protected function superAdmin(): Admin
    {
        return Admin::where('email', 'admin@earnplus.local')->first();
    }

    protected function actingAsSuperAdmin(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
    }

    public function test_guests_cannot_open_wallets(): void
    {
        $this->get('/admin/wallets')->assertRedirect('/admin/login');
    }

    public function test_super_admin_sees_wallet_list_with_totals(): void
    {
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 300, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');

        $this->actingAsSuperAdmin();
        $response = $this->get('/admin/wallets');

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee('300');
    }

    public function test_wallet_search_filters_users(): void
    {
        $coins = app(CoinService::class);
        $alice = User::factory()->create(['name' => 'Alice Wonder', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);
        $coins->credit($alice, 10, CoinTransaction::SOURCE_SIGNUP_BONUS, 'sa');
        $coins->credit($bob, 10, CoinTransaction::SOURCE_SIGNUP_BONUS, 'sb');

        $this->actingAsSuperAdmin();

        $response = $this->get('/admin/wallets?q=alice');
        $response->assertOk();
        $response->assertSee('Alice Wonder');
        $response->assertDontSee('Bob Builder');
    }

    public function test_super_admin_can_credit_with_mandatory_reason(): void
    {
        $user = User::factory()->create();

        $this->actingAsSuperAdmin();
        $response = $this->post(route('admin.wallets.adjust', $user), [
            'direction' => 'credit',
            'amount' => 500,
            'reason' => 'Goodwill bonus for testing',
        ]);

        $response->assertRedirect(route('admin.wallets.show', $user));
        $this->assertSame(500, $user->fresh()->coinBalance());

        $tx = CoinTransaction::where('user_id', $user->id)->first();
        $this->assertSame('admin_adjust', $tx->source);
        $this->assertSame('Goodwill bonus for testing', $tx->meta['reason']);
        $this->assertSame($this->superAdmin()->id, $tx->meta['admin_id']);
        $this->assertSame((string) $this->superAdmin()->id, $tx->reference);
    }

    public function test_adjust_requires_reason(): void
    {
        $user = User::factory()->create();

        $this->actingAsSuperAdmin();
        $response = $this->post(route('admin.wallets.adjust', $user), [
            'direction' => 'credit',
            'amount' => 500,
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_debit_beyond_balance_is_rejected(): void
    {
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 100, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');

        $this->actingAsSuperAdmin();
        $response = $this->post(route('admin.wallets.adjust', $user), [
            'direction' => 'debit',
            'amount' => 999,
            'reason' => 'Correction',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(100, $user->fresh()->coinBalance());
    }

    public function test_plain_admin_cannot_adjust(): void
    {
        $plain = Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($plain, 'admin');
        $response = $this->post(route('admin.wallets.adjust', $user), [
            'direction' => 'credit',
            'amount' => 100,
            'reason' => 'Trying anyway',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, $user->fresh()->coinBalance());
    }

    public function test_plain_admin_can_view_wallets_and_log(): void
    {
        $plain = Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 42, CoinTransaction::SOURCE_SPIN, 'k1');

        $this->actingAs($plain, 'admin');

        $this->get('/admin/wallets')->assertOk();
        $this->get(route('admin.wallets.show', $user))->assertOk()->assertSee('42');
    }

    public function test_transaction_log_filters_work(): void
    {
        $user = User::factory()->create();
        $coins = app(CoinService::class);
        $coins->credit($user, 50, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');
        $coins->debit($user, 20, CoinTransaction::SOURCE_WITHDRAWAL, 'k2');

        $this->actingAsSuperAdmin();

        $response = $this->get(route('admin.wallets.show', [$user, 'type' => 'debit']));
        $response->assertOk();
        // The source filter dropdown always lists every source label, so
        // assert on the row amounts instead of labels.
        $response->assertSee('−20');
        $response->assertDontSee('+50');

        $response = $this->get(route('admin.wallets.show', [$user, 'source' => 'signup_bonus']));
        $response->assertOk();
        $response->assertSee('+50');
        $response->assertDontSee('−20');
    }

    public function test_admin_dashboard_links_to_wallets(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.wallets.index'));
    }

    public function test_setting_change_reflects_in_conversion(): void
    {
        Setting::set('coins_per_rupee', '200', 'coins');

        $user = User::factory()->create();
        app(CoinService::class)->credit($user, 400, CoinTransaction::SOURCE_SIGNUP_BONUS, 'k1');

        $this->assertSame(2.0, $user->fresh()->rupeeBalance());
    }
}
