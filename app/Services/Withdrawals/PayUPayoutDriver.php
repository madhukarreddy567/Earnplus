<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\Http;

/**
 * PayU Payouts driver.
 *
 * Activates ONLY when merchant_key + merchant_salt are present in the
 * method's DB config — otherwise falls back to manual. The request is
 * signed with the merchant salt (SHA-512) per PayU's payout API.
 */
class PayUPayoutDriver implements PayoutDriver
{
    public function __construct(protected WithdrawalMethod $method)
    {
    }

    public function isLive(): bool
    {
        $config = $this->method->config ?? [];

        return ! empty($config['payu_merchant_key']) && ! empty($config['payu_merchant_salt']);
    }

    public function payout(Withdrawal $withdrawal): array
    {
        if (! $this->isLive()) {
            return array_merge(
                (new ManualPayoutDriver($this->method))->payout($withdrawal),
                ['message' => 'PayU keys missing in method config — manual payout required.']
            );
        }

        $config = $this->method->config;
        $details = $withdrawal->details ?? [];
        $base = rtrim($config['payu_base_url'] ?? 'https://payout.payu.in', '/');

        $beneficiary = $details['upi_id']
            ?? $details['mobile']
            ?? ($details['account_no'] ?? '') . '|' . ($details['ifsc'] ?? '');

        $hash = hash('sha512', implode('|', [
            $config['payu_merchant_key'],
            'payout',
            'EP' . $withdrawal->id,
            number_format($withdrawal->net_paise / 100, 2, '.', ''),
            $beneficiary,
            $config['payu_merchant_salt'],
        ]));

        try {
            $response = Http::timeout(30)->asForm()->post($base . '/payout/v1/transfer', [
                'key' => $config['payu_merchant_key'],
                'command' => 'payout',
                'reference_id' => 'EP' . $withdrawal->id,
                'amount' => number_format($withdrawal->net_paise / 100, 2, '.', ''),
                'beneficiary' => $beneficiary,
                'beneficiary_name' => $details['account_holder'] ?? $details['paypal_name'] ?? '',
                'remarks' => 'EarnPlus withdrawal #' . $withdrawal->id,
                'hash' => $hash,
            ]);

            if ($response->successful() && ($response->json('status') ?? 0) == 1) {
                return [
                    'status' => 'completed',
                    'reference' => $response->json('transaction_id'),
                    'message' => null,
                ];
            }

            return [
                'status' => 'failed',
                'reference' => null,
                'message' => 'PayU payout failed: ' . ($response->json('message') ?? 'unknown error'),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'reference' => null, 'message' => 'PayU error: ' . $e->getMessage()];
        }
    }
}
