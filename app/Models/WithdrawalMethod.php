<?php

namespace App\Models;

use App\Services\Withdrawals\CashfreePayoutDriver;
use App\Services\Withdrawals\ManualPayoutDriver;
use App\Services\Withdrawals\PayoutDriver;
use App\Services\Withdrawals\PayUPayoutDriver;
use App\Services\Withdrawals\RazorpayPayoutDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalMethod extends Model
{
    public const TYPE_UPI = 'upi';
    public const TYPE_PAYTM = 'paytm';
    public const TYPE_BANK = 'bank';
    public const TYPE_CASHFREE = 'cashfree';
    public const TYPE_RAZORPAY = 'razorpay';
    public const TYPE_PAYU = 'payu';
    public const TYPE_PAYPAL_MANUAL = 'paypal_manual';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_UPI,
        self::TYPE_PAYTM,
        self::TYPE_BANK,
        self::TYPE_CASHFREE,
        self::TYPE_RAZORPAY,
        self::TYPE_PAYU,
        self::TYPE_PAYPAL_MANUAL,
    ];

    protected $fillable = [
        'type', 'name', 'enabled', 'min_amount', 'max_amount',
        'currency', 'config', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'config' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'method_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isUsd(): bool
    {
        return $this->currency === 'USD';
    }

    /**
     * Smallest-unit → display string, e.g. 1050 paise → "₹10.50".
     */
    public function formatAmount(int $smallestUnit): string
    {
        $symbol = $this->isUsd() ? '$' : '₹';

        return $symbol . number_format($smallestUnit / 100, 2);
    }

    /**
     * Resolve the payout driver for this method. API-backed drivers
     * only activate when their keys are configured — otherwise they
     * transparently fall back to manual processing.
     */
    public function driver(): PayoutDriver
    {
        return match ($this->type) {
            self::TYPE_CASHFREE => new CashfreePayoutDriver($this),
            self::TYPE_RAZORPAY => new RazorpayPayoutDriver($this),
            self::TYPE_PAYU => new PayUPayoutDriver($this),
            default => new ManualPayoutDriver($this),
        };
    }

    /**
     * Which payout-detail fields the user must fill for this method.
     *
     * @return list<string>
     */
    public function detailFields(): array
    {
        return match ($this->type) {
            self::TYPE_UPI, self::TYPE_CASHFREE, self::TYPE_RAZORPAY, self::TYPE_PAYU => ['upi_id'],
            self::TYPE_PAYTM => ['mobile'],
            self::TYPE_BANK => ['account_holder', 'account_no', 'ifsc'],
            self::TYPE_PAYPAL_MANUAL => ['paypal_name', 'paypal_email'],
            default => [],
        };
    }

    public function detailLabel(string $field): string
    {
        return match ($field) {
            'upi_id' => 'UPI ID',
            'mobile' => 'Mobile number',
            'account_holder' => 'Account holder name',
            'account_no' => 'Account number',
            'ifsc' => 'IFSC code',
            'paypal_name' => 'Full name (PayPal)',
            'paypal_email' => 'PayPal e-mail',
            default => $field,
        };
    }
}
