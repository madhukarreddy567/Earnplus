<?php

namespace Tests\Feature\Withdrawals;

use App\Models\CoinTransaction;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalAdminTest extends TestCase
{
    use RefreshDatabase;
    use CreatesWithdrawals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreatesWithdrawals();
    }

    protected function makePending(int $coins = 5000, int $amount = 1000): Withdrawal
    {
        $user = $this->makeUser($coins);

        return $this->withdrawals->request(
            $user,
            $this->method(WithdrawalMethod::TYPE_UPI),
            $amount,
            $this->upiDetails(),
            $this->ukey('admin-') . uniqid()
        );
    }

    public function test_admin_index_shows_queue(): void
    {
        $this->loginAsAdmin();
        $this->makePending();

        $this->get('/admin/withdrawals')->assertOk()->assertSee('Pending queue');
    }

    public function test_guests_cannot_access_admin(): void
    {
        $this->get('/admin/withdrawals')->assertRedirect('/admin/login');
    }

    public function test_approve_manual_method_marks_processing(): void
    {
        $admin = $this->loginAsAdmin();
        $w = $this->makePending();

        $this->post("/admin/withdrawals/{$w->id}/approve", ['admin_note' => 'paid'])
            ->assertRedirect();

        $w->refresh();
        // UPI driver is manual → processing, admin completes by hand.
        $this->assertSame(Withdrawal::STATUS_PROCESSING, $w->status);
        $this->assertNotNull($w->processed_at);
        $this->assertSame('paid', $w->admin_note);
        $this->assertSame($admin->id, $w->meta['processed_by_admin_id']);
        $this->assertStringContainsString('manual', strtolower($w->meta['driver_message'] ?? ''));
    }

    public function test_reject_refunds_exact_coins(): void
    {
        $this->loginAsAdmin();
        $w = $this->makePending(5000, 1000); // 1000 coins debited → 4000 left

        $this->assertSame(4000, $w->user->fresh()->coinBalance());

        $this->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'bad upi id'])
            ->assertRedirect();

        $w->refresh();
        $this->assertSame(Withdrawal::STATUS_REJECTED, $w->status);
        $this->assertSame('bad upi id', $w->admin_note);
        // Exact refund.
        $this->assertSame(5000, $w->user->fresh()->coinBalance());

        $refund = CoinTransaction::where('source', CoinTransaction::SOURCE_WITHDRAWAL_REFUND)
            ->where('user_id', $w->user_id)->firstOrFail();
        $this->assertSame(1000, $refund->amount);
    }

    public function test_reject_is_idempotent(): void
    {
        $this->loginAsAdmin();
        $w = $this->makePending();

        $this->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x'])->assertRedirect();
        $this->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x'])->assertRedirect();

        // Only one refund transaction exists — no double refund.
        $this->assertSame(1, CoinTransaction::where('source', CoinTransaction::SOURCE_WITHDRAWAL_REFUND)
            ->where('user_id', $w->user_id)->count());
        $this->assertSame(5000, $w->user->fresh()->coinBalance());
    }

    public function test_approve_is_idempotent_on_non_pending(): void
    {
        $this->loginAsAdmin();
        $w = $this->makePending();

        $this->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x'])->assertRedirect();
        // Approving a rejected withdrawal changes nothing.
        $this->post("/admin/withdrawals/{$w->id}/approve")->assertRedirect();

        $this->assertSame(Withdrawal::STATUS_REJECTED, $w->fresh()->status);
    }

    public function test_status_filter(): void
    {
        $this->loginAsAdmin();
        $w = $this->makePending();
        $this->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x']);

        $this->get('/admin/withdrawals?status=rejected')->assertOk()->assertSee('Rejected');
        $this->get('/admin/withdrawals?status=completed')->assertOk()->assertDontSee('testuser@okhdfc');
    }

    public function test_method_filter(): void
    {
        $this->loginAsAdmin();
        $w = $this->makePending();
        $paypal = $this->method(WithdrawalMethod::TYPE_PAYPAL_MANUAL);

        $this->get('/admin/withdrawals?method_id=' . $w->method_id)
            ->assertOk()->assertSee('UPI');
        $this->get('/admin/withdrawals?method_id=' . $paypal->id)
            ->assertOk()->assertDontSee('testuser@okhdfc');
    }

    public function test_method_enable_disable(): void
    {
        $this->loginAsAdmin();
        $method = $this->method(WithdrawalMethod::TYPE_PAYTM);

        $this->put("/admin/withdrawals/methods/{$method->id}", [
            'name' => 'Paytm',
            'min_amount' => 1000,
            'max_amount' => 1000000,
            'sort_order' => 2,
            // 'enabled' checkbox omitted = disabled
        ])->assertRedirect();

        $this->assertFalse($method->fresh()->enabled);
    }

    public function test_method_config_update(): void
    {
        $this->loginAsAdmin();
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);

        $this->put("/admin/withdrawals/methods/{$method->id}", [
            'name' => 'Cashfree (auto)',
            'enabled' => '1',
            'min_amount' => 2000,
            'max_amount' => 2000000,
            'sort_order' => 4,
            'config' => '{"cashfree_client_id":"abc","cashfree_env":"prod"}',
        ])->assertRedirect();

        $method->refresh();
        $this->assertTrue($method->enabled);
        $this->assertSame(2000, $method->min_amount);
        $this->assertSame('abc', $method->config['cashfree_client_id']);
        $this->assertSame('prod', $method->config['cashfree_env']);
    }

    public function test_method_config_rejects_bad_json(): void
    {
        $this->loginAsAdmin();
        $method = $this->method(WithdrawalMethod::TYPE_CASHFREE);

        $this->put("/admin/withdrawals/methods/{$method->id}", [
            'name' => 'Cashfree (auto)',
            'min_amount' => 1000,
            'max_amount' => 1000000,
            'sort_order' => 4,
            'config' => '{not json',
        ])->assertSessionHas('error');
    }

    public function test_admin_dashboard_has_withdrawals_card(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin')->assertOk()->assertSee('Withdrawals');
    }
}
