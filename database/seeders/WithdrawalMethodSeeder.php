<?php

namespace Database\Seeders;

use App\Models\WithdrawalMethod;
use Illuminate\Database\Seeder;

/**
 * Seeds the payout methods. API-backed methods (Cashfree / Razorpay /
 * PayU) start DISABLED — they activate only after the owner pastes
 * real API keys into the method config in the admin panel.
 * Amounts are in the smallest currency unit (paise / cents).
 */
class WithdrawalMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'type' => WithdrawalMethod::TYPE_UPI,
                'name' => 'UPI',
                'enabled' => true,
                'min_amount' => 1000,   // ₹10
                'max_amount' => 1000000, // ₹10,000
                'currency' => 'INR',
                'config' => null,
                'sort_order' => 1,
            ],
            [
                'type' => WithdrawalMethod::TYPE_PAYTM,
                'name' => 'Paytm',
                'enabled' => true,
                'min_amount' => 1000,
                'max_amount' => 1000000,
                'currency' => 'INR',
                'config' => null,
                'sort_order' => 2,
            ],
            [
                'type' => WithdrawalMethod::TYPE_BANK,
                'name' => 'Bank transfer',
                'enabled' => true,
                'min_amount' => 1000,
                'max_amount' => 5000000, // ₹50,000
                'currency' => 'INR',
                'config' => null,
                'sort_order' => 3,
            ],
            [
                'type' => WithdrawalMethod::TYPE_CASHFREE,
                'name' => 'Cashfree (auto)',
                'enabled' => false,
                'min_amount' => 1000,
                'max_amount' => 1000000,
                'currency' => 'INR',
                'config' => [
                    'cashfree_client_id' => '',
                    'cashfree_client_secret' => '',
                    'cashfree_env' => 'sandbox',
                ],
                'sort_order' => 4,
            ],
            [
                'type' => WithdrawalMethod::TYPE_RAZORPAY,
                'name' => 'RazorpayX (auto)',
                'enabled' => false,
                'min_amount' => 1000,
                'max_amount' => 1000000,
                'currency' => 'INR',
                'config' => [
                    'razorpay_key_id' => '',
                    'razorpay_key_secret' => '',
                    'razorpay_account_number' => '',
                ],
                'sort_order' => 5,
            ],
            [
                'type' => WithdrawalMethod::TYPE_PAYU,
                'name' => 'PayU (auto)',
                'enabled' => false,
                'min_amount' => 1000,
                'max_amount' => 1000000,
                'currency' => 'INR',
                'config' => [
                    'payu_merchant_key' => '',
                    'payu_merchant_salt' => '',
                    'payu_base_url' => 'https://payout.payu.in',
                ],
                'sort_order' => 6,
            ],
            [
                'type' => WithdrawalMethod::TYPE_PAYPAL_MANUAL,
                'name' => 'PayPal (manual)',
                'enabled' => true,
                'min_amount' => 100,   // $1
                'max_amount' => 50000, // $500
                'currency' => 'USD',
                'config' => null,
                'sort_order' => 7,
            ],
        ];

        foreach ($methods as $method) {
            WithdrawalMethod::updateOrCreate(
                ['type' => $method['type']],
                $method
            );
        }
    }
}
