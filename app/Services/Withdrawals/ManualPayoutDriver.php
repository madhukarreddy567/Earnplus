<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;

/**
 * Default driver: the payout is completed by a human in the admin
 * panel. Used for UPI / Paytm / bank / manual-PayPal and as the
 * fallback for API drivers whose keys are not configured.
 */
class ManualPayoutDriver implements PayoutDriver
{
    public function __construct(protected WithdrawalMethod $method)
    {
    }

    public function payout(Withdrawal $withdrawal): array
    {
        return [
            'status' => 'manual',
            'reference' => null,
            'message' => "Manual payout via {$this->method->name} — admin completes it by hand.",
        ];
    }

    public function isLive(): bool
    {
        return false;
    }
}
