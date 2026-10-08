<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    public const SOURCE_SIGNUP_BONUS = 'signup_bonus';
    public const SOURCE_REFERRAL_BONUS = 'referral_bonus';
    public const SOURCE_DAILY_CHECKIN = 'daily_checkin';
    public const SOURCE_SPIN = 'spin';
    public const SOURCE_TASK_OFFERWALL = 'task_offerwall';
    public const SOURCE_REWARDED_AD = 'rewarded_ad';
    public const SOURCE_ADMIN_ADJUST = 'admin_adjust';
    public const SOURCE_WITHDRAWAL = 'withdrawal';
    public const SOURCE_WITHDRAWAL_REFUND = 'withdrawal_refund';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_SIGNUP_BONUS,
        self::SOURCE_REFERRAL_BONUS,
        self::SOURCE_DAILY_CHECKIN,
        self::SOURCE_SPIN,
        self::SOURCE_TASK_OFFERWALL,
        self::SOURCE_REWARDED_AD,
        self::SOURCE_ADMIN_ADJUST,
        self::SOURCE_WITHDRAWAL,
        self::SOURCE_WITHDRAWAL_REFUND,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'source',
        'reference',
        'meta',
        'balance_after',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    /**
     * Human label for a source key (used in the UI).
     */
    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            self::SOURCE_SIGNUP_BONUS => 'Signup bonus',
            self::SOURCE_REFERRAL_BONUS => 'Referral bonus',
            self::SOURCE_DAILY_CHECKIN => 'Daily check-in',
            self::SOURCE_SPIN => 'Spin wheel',
            self::SOURCE_TASK_OFFERWALL => 'Offerwall task',
            self::SOURCE_REWARDED_AD => 'Rewarded ad',
            self::SOURCE_ADMIN_ADJUST => 'Admin adjustment',
            self::SOURCE_WITHDRAWAL => 'Withdrawal',
            self::SOURCE_WITHDRAWAL_REFUND => 'Withdrawal refund',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }
}
