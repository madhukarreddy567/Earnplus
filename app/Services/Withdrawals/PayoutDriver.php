<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;

/**
 * Pluggable payout driver contract.
 *
 * Every driver either talks to a real payout API (when its keys are
 * configured in the method's DB config) or transparently reports
 * `manual` so the admin completes the payout by hand.
 *
 * @return array{status: string, reference: ?string, message: ?string}
 *         status is one of: completed | processing | failed | manual
 */
interface PayoutDriver
{
    public function payout(Withdrawal $withdrawal): array;

    /**
     * True when this driver will actually call a live API.
     */
    public function isLive(): bool;
}
