<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending spin reward awaiting a verified Unity rewarded-ad view.
 *
 * Lifecycle: pending → verified (Unity S2S callback arrived) → claimed
 * (POST /api/spin/claim credited the wallet). Tokens expire; expired and
 * already-claimed tokens can never credit.
 */
class SpinClaim extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'spin_history_id',
        'amount',
        'token',
        'status',
        'unity_sid',
        'unity_verified_at',
        'claimed_at',
        'expires_at',
        'attempts',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'attempts' => 'integer',
            'unity_verified_at' => 'datetime',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spinHistory(): BelongsTo
    {
        return $this->belongsTo(SpinHistory::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isClaimed(): bool
    {
        return $this->status === self::STATUS_CLAIMED;
    }

    public function isAdVerified(): bool
    {
        return $this->unity_verified_at !== null;
    }
}
