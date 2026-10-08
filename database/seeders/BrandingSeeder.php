<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\BrandingService;
use Illuminate\Database\Seeder;

/**
 * Phase 8: branding + design defaults.
 *
 * Uses firstOrCreate semantics — seeded defaults never overwrite values
 * the admin already customized. Branding asset keys are intentionally
 * NOT seeded: their absence means "use the built-in default".
 */
class BrandingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BrandingService::designDefaults() as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'group' => 'design']
            );
        }
    }
}
