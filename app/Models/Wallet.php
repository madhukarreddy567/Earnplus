<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'coins', 'lifetime_earned'];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'lifetime_earned' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rupee equivalent of the coin balance (coins_per_rupee is admin-editable).
     */
    public function rupees(): float
    {
        return coins_to_rupees($this->coins);
    }
}
