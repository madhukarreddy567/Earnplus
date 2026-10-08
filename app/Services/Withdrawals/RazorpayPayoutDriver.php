<?php

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\Http;

/**
 * RazorpayX Payouts driver.
 *
 * Real API: POST /v1/payouts with HTTP basic auth (key_id:key_secret).
 * Activates ONLY when key_id + key_secret + account_number are present
 * in the method's DB config — otherwise falls back to manual.
 */
class RazorpayPayoutDriver implements PayoutDriver
{
    public function __construct(protected WithdrawalMethod $method)
    {
    }

    public function isLive(): bool
    {
        $config = $this->method->config ?? [];

        return ! empty($config['razorpay_key_id'])
            && ! empty($config['razorpay_key_secret'])
            && ! empty($config['razorpay_account_number']);
    }

    public function payout(Withdrawal $withdrawal): array
    {
        if (! $this->isLive()) {
            return array_merge(
                (new ManualPayoutDriver($this->method))->payout($withdrawal),
                ['message' => 'Razorpay keys missing in method config — manual payout required.']
            );
        }

        $config = $this->method->config;
        $details = $withdrawal->details ?? [];

        try {
            $response = Http::timeout(30)
                ->withBasicAuth($config['razorpay_key_id'], $config['razorpay_key_secret'])
                ->post('https://api.razorpay.com/v1/payouts', [
                    'account_number' => $config['razorpay_account_number'],
                    'amount' => $withdrawal->net_paise,
                    'currency' => 'INR',
                    'mode' => ! empty($details['upi_id']) ? 'UPI' : 'IMPS',
                    'purpose' => 'payout',
                    'fund_account' => [
                        'account_type' => ! empty($details['upi_id']) ? 'vpa' : 'bank_account',
                        'vpa' => ['address' => $details['upi_id'] ?? null],
                        'bank_account' => [
                            'name' => $details['account_holder'] ?? null,
                            'account_number' => $details['account_no'] ?? null,
                            'ifsc' => $details['ifsc'] ?? null,
                        ],
                    ],
                    'queue_if_low' => true,
                    'reference_id' => 'EP' . $withdrawal->id,
                    'narration' => 'EarnPlus withdrawal #' . $withdrawal->id,
                ]);

            if ($response->successful() && ! empty($response->json('id'))) {
                $status = $response->json('status');

                return [
                    'status' => in_array($status, ['processed'], true) ? 'completed' : 'processing',
                    'reference' => $response->json('id'),
                    'message' => null,
                ];
            }

            $error = $response->json('error.description') ?? 'unknown error';

            return ['status' => 'failed', 'reference' => null, 'message' => 'Razorpay payout failed: ' . $error];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'reference' => null, 'message' => 'Razorpay error: ' . $e->getMessage()];
        }
    }
}
