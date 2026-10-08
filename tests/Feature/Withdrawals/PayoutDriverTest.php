<?php

namespace Tests\Feature\Withdrawals;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\Withdrawals\CashfreePayoutDriver;
use App\Services\Withdrawals\ManualPayoutDriver;
use App\Services\Withdrawals\PayUPayoutDriver;
use App\Services\Withdrawals\RazorpayPayoutDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutDriverTest extends TestCase
{
    use RefreshDatabase;
    use CreatesWithdrawals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesWithdrawals();
    }

    protected function makeWithdrawal(string $type = WithdrawalMethod::TYPE_UPI): Withdrawal
    {
        $user = $this->makeUser(10000);
        $method = $this->method($type);
        // API methods seed disabled; the driver tests need them on.
        $method->update(['enabled' => true]);

        return $this->withdrawals->request(
            $user,
            $method->fresh(),
            2000,
            $this->upiDetails(),
            $this->ukey('driver-') . uniqid()
        );
    }

    public function test_manual_driver_always_manual(): void
    {
        $w = $this->makeWithdrawal();
        $result = (new ManualPayoutDriver($w->method))->payout($w);

        $this->assertSame('manual', $result['status']);
        $this->assertFalse((new ManualPayoutDriver($w->method))->isLive());
    }

    public function test_cashfree_without_keys_falls_back_to_manual(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);
        $driver = new CashfreePayoutDriver($method);

        $this->assertFalse($driver->isLive());

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_CASHFREE);
        $result = $driver->payout($w);

        $this->assertSame('manual', $result['status']);
        $this->assertStringContainsString('keys missing', strtolower($result['message']));
    }

    public function test_cashfree_with_keys_calls_api(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);
        $method->update(['config' => [
            'cashfree_client_id' => 'test-id',
            'cashfree_client_secret' => 'test-secret',
            'cashfree_env' => 'sandbox',
        ]]);

        $driver = new CashfreePayoutDriver($method->fresh());
        $this->assertTrue($driver->isLive());

        Http::fake([
            '*/authorize' => Http::response(['data' => ['token' => 'tok123']], 200),
            '*/requestTransfer' => Http::response([
                'status' => 'SUCCESS',
                'data' => ['referenceId' => 'CF123'],
            ], 200),
        ]);

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_CASHFREE);
        $result = $driver->payout($w);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('CF123', $result['reference']);
    }

    public function test_cashfree_api_failure(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);
        $method->update(['config' => [
            'cashfree_client_id' => 'test-id',
            'cashfree_client_secret' => 'test-secret',
        ]]);

        Http::fake([
            '*/authorize' => Http::response(['message' => 'bad'], 401),
        ]);

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_CASHFREE);
        $result = (new CashfreePayoutDriver($method->fresh()))->payout($w);

        $this->assertSame('failed', $result['status']);
    }

    public function test_razorpay_with_keys_calls_api(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_RAZORPAY);
        $method->update(['config' => [
            'razorpay_key_id' => 'rzp_test',
            'razorpay_key_secret' => 'secret',
            'razorpay_account_number' => '123456',
        ]]);

        $driver = new RazorpayPayoutDriver($method->fresh());
        $this->assertTrue($driver->isLive());

        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'pout_123', 'status' => 'processed'], 200),
        ]);

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_RAZORPAY);
        $result = $driver->payout($w);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('pout_123', $result['reference']);
    }

    public function test_razorpay_queued_status_maps_to_processing(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_RAZORPAY);
        $method->update(['config' => [
            'razorpay_key_id' => 'rzp_test',
            'razorpay_key_secret' => 'secret',
            'razorpay_account_number' => '123456',
        ]]);

        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'pout_456', 'status' => 'queued'], 200),
        ]);

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_RAZORPAY);
        $result = (new RazorpayPayoutDriver($method->fresh()))->payout($w);

        $this->assertSame('processing', $result['status']);
    }

    public function test_razorpay_without_keys_falls_back(): void
    {
        $driver = new RazorpayPayoutDriver($this->method(WithdrawalMethod::TYPE_RAZORPAY));

        $this->assertFalse($driver->isLive());
        $this->assertSame('manual', $driver->payout($this->makeWithdrawal(WithdrawalMethod::TYPE_RAZORPAY))['status']);
    }

    public function test_payu_with_keys_calls_api(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_PAYU);
        $method->update(['config' => [
            'payu_merchant_key' => 'key',
            'payu_merchant_salt' => 'salt',
            'payu_base_url' => 'https://payout.payu.in',
        ]]);

        $driver = new PayUPayoutDriver($method->fresh());
        $this->assertTrue($driver->isLive());

        Http::fake([
            'payout.payu.in/*' => Http::response(['status' => 1, 'transaction_id' => 'PU123'], 200),
        ]);

        $w = $this->makeWithdrawal(WithdrawalMethod::TYPE_PAYU);
        $result = $driver->payout($w);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('PU123', $result['reference']);
    }

    public function test_payu_without_keys_falls_back(): void
    {
        $driver = new PayUPayoutDriver($this->method(WithdrawalMethod::TYPE_PAYU));

        $this->assertFalse($driver->isLive());
        $this->assertSame('manual', $driver->payout($this->makeWithdrawal(WithdrawalMethod::TYPE_PAYU))['status']);
    }

    public function test_upi_resolves_to_manual_driver(): void
    {
        $method = $this->method(WithdrawalMethod::TYPE_UPI);

        $this->assertInstanceOf(ManualPayoutDriver::class, $method->driver());
    }

    public function test_admin_approve_with_live_cashfree_completes(): void
    {
        $admin = $this->loginAsAdmin();
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);
        $method->update([
            'enabled' => true,
            'config' => [
                'cashfree_client_id' => 'test-id',
                'cashfree_client_secret' => 'test-secret',
            ],
        ]);

        Http::fake([
            '*/authorize' => Http::response(['data' => ['token' => 'tok']], 200),
            '*/requestTransfer' => Http::response([
                'status' => 'SUCCESS',
                'data' => ['referenceId' => 'CF999'],
            ], 200),
        ]);

        $user = $this->makeUser(10000);
        $w = $this->withdrawals->request($user, $method->fresh(), 2000, $this->upiDetails(), $this->ukey('live-') . uniqid());

        $this->post("/admin/withdrawals/{$w->id}/approve")->assertRedirect();

        $w->refresh();
        $this->assertSame(Withdrawal::STATUS_COMPLETED, $w->status);
        $this->assertSame('CF999', $w->payout_reference);
        $this->assertTrue($w->meta['driver_live']);
    }
}
