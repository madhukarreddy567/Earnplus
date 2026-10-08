<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\LicenseService;
use Illuminate\Database\Seeder;

/**
 * Phase 13: license defaults.
 *
 * Uses firstOrCreate semantics — seeded defaults never overwrite values
 * the admin already configured. Fresh installs start in DEVELOPMENT
 * mode (lenient): the owner flips to production on the admin license
 * page after setting the licensed domain.
 *
 * The signature seed is the build signature constant — it must match
 * LicenseService::SIGNATURE. Only the constant is stored; no license
 * key values are invented anywhere.
 */
class LicenseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'app_mode' => LicenseService::MODE_DEVELOPMENT,
            'license_signature' => LicenseService::SIGNATURE,
            'license_status' => 'active',
            'licensed_domain' => '',
            'license_domain_aliases' => '',
            'license_violation_block_threshold' => '10',
            'license_remote_kill_enabled' => '0',
            'license_locked' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'license']
            );
        }
    }
}
