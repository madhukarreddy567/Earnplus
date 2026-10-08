<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferwallConversion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CREDITED,
        self::STATUS_REJECTED,
        self::STATUS_DUPLICATE,
    ];

    protected $fillable = [
        'provider_id',
        'user_id',
        'provider_tx_id',
        'click_uid',
        'payout_coins',
        'user_coins',
        'status',
        'raw_payload',
        'meta',
        'credited_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'meta' => 'array',
        'credited_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OfferwallProvider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
