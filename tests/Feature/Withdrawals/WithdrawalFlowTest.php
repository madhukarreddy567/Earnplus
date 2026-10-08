<?php

namespace Tests\Feature\Withdrawals;

use App\Models\Setting;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesWithdrawals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesWithdrawals();
    }

    protected function withdrawPayload(WithdrawalMethod $method, int $amount, array $details, string $key): array
    {
        return [
            'method_id' => $method->id,
            'amount' => $amount,
            'idempotency_key' => $key,
            'details' => $details,
        ];
    }

    public function test_withdraw_page_renders(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);

        $this->get('/withdraw')
            ->assertOk()
            ->assertSee('Withdraw')
            ->assertSee('UPI')
            ->assertSee('₹10');
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/withdraw')->assertRedirect('/login');
        $this->post('/withdraw', [])->assertRedirect('/login');
    }

    public function test_successful_upi_request(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $response = $this->post('/withdraw', $this->withdrawPayload(
            $method, 1000, $this->upiDetails(), $this->ukey('success-1')
        ));

        $response->assertRedirect();
        $w = Withdrawal::where('idempotency_key', $this->ukey('success-1'))->firstOrFail();
        $this->assertSame(Withdrawal::STATUS_PENDING, $w->status);
        $this->assertSame(1000, $w->coins_debited);
        $this->assertSame(1000, $w->amount_paise);
        $this->assertSame(1000, $w->net_paise);
        $this->assertSame('INR', $w->currency);
        $this->assertSame('testuser@okhdfc', $w->details['upi_id']);
        // Coins debited immediately.
        $this->assertSame(4000, $user->fresh()->coinBalance());
    }

    public function test_custom_amount_within_limits(): void
    {
        $user = $this->makeUser(100000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_BANK);

        $this->post('/withdraw', $this->withdrawPayload($method, 54321, [
            'account_holder' => 'Test User',
            'account_no' => '123456789012',
            'ifsc' => 'HDFC0001234',
        ], $this->ukey('custom-1')))->assertRedirect();

        $w = Withdrawal::where('idempotency_key', $this->ukey('custom-1'))->firstOrFail();
        $this->assertSame(54321, $w->amount_paise);
        $this->assertSame(54321, $w->coins_debited);
    }

    public function test_amount_below_min_rejected(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI); // min 1000 paise

        $this->post('/withdraw', $this->withdrawPayload(
            $method, 500, $this->upiDetails(), $this->ukey('min-1')
        ))->assertSessionHas('error');

        $this->assertNull(Withdrawal::where('idempotency_key', $this->ukey('min-1'))->first());
        $this->assertSame(5000, $user->fresh()->coinBalance());
    }

    public function test_amount_above_max_rejected(): void
    {
        $user = $this->makeUser(2000000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI); // max 1000000 paise

        $this->post('/withdraw', $this->withdrawPayload(
            $method, 1000001, $this->upiDetails(), $this->ukey('max-1')
        ))->assertSessionHas('error');

        $this->assertNull(Withdrawal::where('idempotency_key', $this->ukey('max-1'))->first());
    }

    public function test_insufficient_balance_rejected(): void
    {
        $user = $this->makeUser(100); // only 100 coins
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->post('/withdraw', $this->withdrawPayload(
            $method, 1000, $this->upiDetails(), $this->ukey('poor-1')
        ))->assertSessionHas('error');

        $this->assertSame(100, $user->fresh()->coinBalance());
    }

    public function test_double_submit_is_idempotent(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);
        $payload = $this->withdrawPayload($method, 1000, $this->upiDetails(), $this->ukey('double-1'));

        $this->post('/withdraw', $payload)->assertRedirect();
        $this->post('/withdraw', $payload)->assertRedirect();

        $this->assertSame(1, Withdrawal::where('idempotency_key', $this->ukey('double-1'))->count());
        // Debited exactly once.
        $this->assertSame(4000, $user->fresh()->coinBalance());
    }

    public function test_daily_limit_enforced(): void
    {
        Setting::set('withdrawal_max_per_day', '2', 'withdrawals');
        $user = $this->makeUser(50000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->post('/withdraw', $this->withdrawPayload($method, 1000, $this->upiDetails(), $this->ukey('day-1')))->assertRedirect();
        $this->post('/withdraw', $this->withdrawPayload($method, 1000, $this->upiDetails(), $this->ukey('day-2')))->assertRedirect();
        $this->post('/withdraw', $this->withdrawPayload($method, 1000, $this->upiDetails(), $this->ukey('day-3')))
            ->assertSessionHas('error');

        $this->assertSame(2, Withdrawal::where('user_id', $user->id)->count());
    }

    public function test_invalid_upi_id_rejected(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->post('/withdraw', $this->withdrawPayload(
            $method, 1000, ['upi_id' => 'not-a-upi-id'], $this->ukey('badupi-1')
        ))->assertSessionHasErrors('details.upi_id');
    }

    public function test_invalid_ifsc_rejected(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_BANK);

        $this->post('/withdraw', $this->withdrawPayload($method, 1000, [
            'account_holder' => 'Test User',
            'account_no' => '123456789',
            'ifsc' => 'BAD',
        ], $this->ukey('badifsc-1')))->assertSessionHasErrors('details.ifsc');
    }

    public function test_invalid_paypal_email_rejected(): void
    {
        $user = $this->makeUser(50000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_PAYPAL_MANUAL);

        $this->post('/withdraw', $this->withdrawPayload($method, 500, [
            'paypal_name' => 'Test User',
            'paypal_email' => 'not-an-email',
        ], $this->ukey('badpp-1')))->assertSessionHasErrors('details.paypal_email');
    }

    public function test_paypal_usd_request(): void
    {
        $user = $this->makeUser(50000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_PAYPAL_MANUAL);

        $this->post('/withdraw', $this->withdrawPayload($method, 500, [ // $5.00
            'paypal_name' => 'Test User',
            'paypal_email' => 'test@example.com',
        ], $this->ukey('pp-1')))->assertRedirect();

        $w = Withdrawal::where('idempotency_key', $this->ukey('pp-1'))->firstOrFail();
        $this->assertSame('USD', $w->currency);
        $this->assertSame(500, $w->net_usd_cents);
    }

    public function test_disabled_method_blocks_request(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_PAYTM);
        $method->update(['enabled' => false]);

        $this->post('/withdraw', [
            'method_id' => $method->id,
            'amount' => 1000,
            'idempotency_key' => $this->ukey('disabled-1'),
            'details' => ['mobile' => '9876543210'],
        ])->assertSessionHas('error');

        $this->assertNull(Withdrawal::where('idempotency_key', $this->ukey('disabled-1'))->first());
    }

    public function test_withdrawals_disabled_toggle_blocks_everything(): void
    {
        Setting::set('withdrawals_enabled', '0', 'features');
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->post('/withdraw', $this->withdrawPayload(
            $method, 1000, $this->upiDetails(), $this->ukey('toggle-1')
        ))->assertSessionHas('error');

        $this->get('/withdraw')->assertOk()->assertSee('paused');
    }

    public function test_quote_endpoint(): void
    {
        $user = $this->makeUser(5000);
        $this->actingAs($user);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->postJson('/withdraw/quote', [
            'method_id' => $method->id,
            'amount' => 2000,
        ])->assertOk()->assertJsonPath('coins', 2000);
    }

    public function test_user_cannot_view_others_withdrawal(): void
    {
        $a = $this->makeUser(5000);
        $b = $this->makeUser(5000);
        $this->actingAs($a);
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $w = $this->withdrawals->request($b, $method, 1000, $this->upiDetails(), $this->ukey('other-1'));

        $this->get('/withdraw/' . $w->id)->assertForbidden();
    }

    public function test_dashboard_tile_shows_withdrawable(): void
    {
        $user = $this->makeUser(2550);
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk()->assertSee('₹25.50', false);
    }
}
