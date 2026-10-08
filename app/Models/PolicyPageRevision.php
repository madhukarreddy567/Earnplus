<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An archived version of a policy page — kept on every save so the
 * owner can review history and roll back a bad edit.
 */
class PolicyPageRevision extends Model
{
    protected $fillable = [
        'policy_page_id',
        'version',
        'body_html',
        'was_published',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'was_published' => 'boolean',
        ];
    }

    public function policyPage(): BelongsTo
    {
        return $this->belongsTo(PolicyPage::class);
    }
}
