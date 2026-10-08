<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\Http;

/**
 * Cashfree Payouts driver.
 *
 * Real API: first authorize with clientId/clientSecret, then
 * requestTransfer. Activates ONLY when client_id + client_secret are
 * present in the method's DB config — otherwise falls back to manual
 * with a clear notice. Never invents credentials.
 */
class CashfreePayoutDriver implements PayoutDriver
{
    public function __construct(protected WithdrawalMethod $method)
    {
    }

    public function isLive(): bool
    {
        $config = $this->method->config ?? [];

        return ! empty($config['cashfree_client_id']) && ! empty($config['cashfree_client_secret']);
    }

    public function payout(Withdrawal $withdrawal): array
    {
        if (! $this->isLive()) {
            return array_merge(
                (new ManualPayoutDriver($this->method))->payout($withdrawal),
                ['message' => 'Cashfree keys missing in method config — manual payout required.']
            );
        }

        $config = $this->method->config;
        $base = ($config['cashfree_env'] ?? 'sandbox') === 'prod'
            ? 'https://payout-api.cashfree.com/payout/v1'
            : 'https://payout-gamma.cashfree.com/payout/v1';

        try {
            $auth = Http::timeout(20)->post($base . '/authorize', [
                'clientId' => $config['cashfree_client_id'],
                'clientSecret' => $config['cashfree_client_secret'],
            ]);

            if (! $auth->successful() || empty($auth->json('data.token'))) {
                return ['status' => 'failed', 'reference' => null, 'message' => 'Cashfree authorization failed.'];
            }

            $details = $withdrawal->details ?? [];
            $transfer = Http::timeout(30)
                ->withToken($auth->json('data.token'))
                ->post($base . '/requestTransfer', [
                    'beneId' => 'earnplus-' . $withdrawal->id,
                    'amount' => number_format($withdrawal->net_paise / 100, 2, '.', ''),
                    'transferId' => 'EP' . $withdrawal->id . time(),
                    'transferMode' => $this->transferMode($details),
                    'remarks' => 'EarnPlus withdrawal #' . $withdrawal->id,
                    'upi' => ['vpa' => $details['upi_id'] ?? null],
                    'bank' => [
                        'accountNumber' => $details['account_no'] ?? null,
                        'ifsc' => $details['ifsc'] ?? null,
                        'name' => $details['account_holder'] ?? null,
                    ],
                ]);

            if ($transfer->successful() && ($transfer->json('status') ?? '') === 'SUCCESS') {
                return [
                    'status' => 'completed',
                    'reference' => $transfer->json('data.referenceId'),
                    'message' => null,
                ];
            }

            return [
                'status' => 'failed',
                'reference' => null,
                'message' => 'Cashfree transfer failed: ' . ($transfer->json('message') ?? 'unknown error'),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'reference' => null, 'message' => 'Cashfree error: ' . $e->getMessage()];
        }
    }

    protected function transferMode(array $details): string
    {
        if (! empty($details['upi_id'])) {
            return 'upi';
        }

        if (! empty($details['account_no'])) {
            return 'banktransfer';
        }

        return 'upi';
    }
}
