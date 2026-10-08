<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

/**
 * Seeds one clearly-labeled demo promotion with dates in the past,
 * so it shows up as expired/inert demo data and never pays out.
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::updateOrCreate(
            ['slug' => 'diwali-dhamaka-demo'],
            [
                'name' => 'Diwali Dhamaka (demo)',
                'enabled' => false,
                'multiplier' => 2.00,
                'scope' => Promotion::SCOPE_GLOBAL,
                'provider_id' => null,
                'starts_at' => '2025-10-18 00:00:00',
                'ends_at' => '2025-10-25 23:59:59',
                'banner_title' => 'Diwali Dhamaka — 2X coins on everything!',
                'banner_subtitle' => 'Every task, spin and check-in pays double during the festival.',
                'badge_text' => '2X',
                'priority' => 10,
            ]
        );
    }
}
