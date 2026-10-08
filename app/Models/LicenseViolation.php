<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 13: license/domain-lock violation log.
 *
 * A violation is recorded whenever the app detects it may be running
 * somewhere it shouldn't (wrong domain), with a tampered signature,
 * or when the kill switch is used. Read-only from the user side —
 * only the admin license page reads this table.
 */
class LicenseViolation extends Model
{
    public const TYPE_DOMAIN_MISMATCH = 'domain_mismatch';
    public const TYPE_SIGNATURE_TAMPER = 'signature_tamper';
    public const TYPE_KILL_SWITCH = 'kill_switch';
    public const TYPE_DOMAIN_UNCONFIGURED = 'domain_unconfigured';

    protected $fillable = ['type', 'ip', 'host', 'details'];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Human-readable labels for the admin filter/page.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_DOMAIN_MISMATCH => 'Domain mismatch',
            self::TYPE_SIGNATURE_TAMPER => 'Signature tamper',
            self::TYPE_KILL_SWITCH => 'Kill switch',
            self::TYPE_DOMAIN_UNCONFIGURED => 'Domain unconfigured',
        ];
    }
}
