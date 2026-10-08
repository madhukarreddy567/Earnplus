<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'user_id', 'method_id', 'coins_debited', 'amount_paise', 'tax_paise',
        'net_paise', 'net_usd_cents', 'currency', 'details', 'status',
        'admin_note', 'idempotency_key', 'payout_reference', 'payout_attempts',
        'next_retry_at', 'meta', 'requested_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'coins_debited' => 'integer',
            'amount_paise' => 'integer',
            'tax_paise' => 'integer',
            'net_paise' => 'integer',
            'net_usd_cents' => 'integer',
            'details' => 'array',
            'meta' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'payout_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawalMethod::class, 'method_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Net payout formatted for display: "₹9.75" or "$1.20".
     */
    public function netFormatted(): string
    {
        if ($this->currency === 'USD') {
            return '$' . number_format(($this->net_usd_cents ?? 0) / 100, 2);
        }

        return '₹' . number_format($this->net_paise / 100, 2);
    }

    public function amountFormatted(): string
    {
        return '₹' . number_format($this->amount_paise / 100, 2);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => $status,
        };
    }
}
