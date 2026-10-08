<?php

namespace Tests\Feature\Withdrawals;

use App\Models\Setting;
use App\Models\WithdrawalMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalMathTest extends TestCase
{
    use RefreshDatabase;
    use CreatesWithdrawals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesWithdrawals();
    }

    public function test_coins_to_paise_at_default_rate(): void
    {
        // 100 coins = ₹1 = 100 paise.
        $this->assertSame(100, $this->withdrawals->coinsToPaise(100));
        $this->assertSame(2550, $this->withdrawals->coinsToPaise(2550));
        $this->assertSame(1, $this->withdrawals->coinsToPaise(1));
    }

    public function test_coins_to_paise_respects_setting(): void
    {
        Setting::set('coins_per_rupee', '200', 'coins');

        // 200 coins = ₹1 now.
        $this->assertSame(100, $this->withdrawals->coinsToPaise(200));
        $this->assertSame(50, $this->withdrawals->coinsToPaise(100));
    }

    public function test_paise_to_coins_rounds_up(): void
    {
        // 101 paise needs 101 coins (ceil, never short).
        $this->assertSame(101, $this->withdrawals->paiseToCoins(101));
        $this->assertSame(1000, $this->withdrawals->paiseToCoins(1000));
    }

    public function test_tax_defaults_to_zero(): void
    {
        $this->assertSame(0, $this->withdrawals->taxPaise(10000));
    }

    public function test_tax_applies_when_enabled(): void
    {
        Setting::set('withdrawal_tax_enabled', '1', 'withdrawals');
        Setting::set('withdrawal_tax_percent', '10', 'withdrawals');

        $this->assertSame(1000, $this->withdrawals->taxPaise(10000));
        // Integer math: 999 * 10 / 100 = 99 (floor via intdiv).
        $this->assertSame(99, $this->withdrawals->taxPaise(999));
    }

    public function test_paise_to_usd_cents(): void
    {
        // 0.012 USD per ₹1 → 10000 paise (₹100) = $1.20 = 120 cents.
        $this->assertSame(120, $this->withdrawals->paiseToUsdCents(10000));
    }

    public function test_quote_inr_method(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_UPI);
        $quote = $this->withdrawals->quote($method, 1000); // ₹10

        $this->assertSame(1000, $quote['coins']);
        $this->assertSame(1000, $quote['amount_paise']);
        $this->assertSame(0, $quote['tax_paise']);
        $this->assertSame(1000, $quote['net_paise']);
        $this->assertNull($quote['net_usd_cents']);
    }

    public function test_quote_with_tax(): void
    {
        Setting::set('withdrawal_tax_enabled', '1', 'withdrawals');
        Setting::set('withdrawal_tax_percent', '5', 'withdrawals');

        $method = $this->method(WithdrawalMethod::TYPE_UPI);
        $quote = $this->withdrawals->quote($method, 2000); // ₹20

        $this->assertSame(100, $quote['tax_paise']);
        $this->assertSame(1900, $quote['net_paise']);
        // Coins are computed on the GROSS amount.
        $this->assertSame(2000, $quote['coins']);
    }

    public function test_quote_usd_method(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_PAYPAL_MANUAL);
        $quote = $this->withdrawals->quote($method, 120); // $1.20

        $this->assertSame(10000, $quote['amount_paise']); // $1.20 ≈ ₹100
        $this->assertSame(120, $quote['net_usd_cents']);
        $this->assertSame(10000, $quote['coins']);
    }

    public function test_all_amounts_are_integers(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_UPI);
        $quote = $this->withdrawals->quote($method, 999);

        foreach (['coins', 'amount_paise', 'tax_paise', 'net_paise'] as $key) {
            $this->assertIsInt($quote[$key], $key);
        }
    }
}
