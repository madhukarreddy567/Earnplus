<?php

namespace Tests\Feature\Coins;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_default_conversion_is_100_coins_per_rupee(): void
    {
        $this->assertSame(100, setting_int('coins_per_rupee', 100));
        $this->assertSame(1.0, coins_to_rupees(100));
        $this->assertSame(2.5, coins_to_rupees(250));
        $this->assertSame(0.0, coins_to_rupees(0));
    }

    /**
     * The user-facing economy is 100 Coins = ₹1. This pins the SEEDED
     * database default (not just the code fallback) so offerwall credits
     * (e.g. TimeWall at 9000 coins/$1 ≈ ₹90) value consistently against
     * withdrawals. The setting stays admin-editable via Setting::set.
     */
    public function test_seeded_default_is_100_coins_per_rupee(): void
    {
        $setting = Setting::where('key', 'coins_per_rupee')->firstOrFail();

        $this->assertSame('100', $setting->value);
        $this->assertSame('coins', $setting->group);
    }

    public function test_conversion_follows_admin_setting(): void
    {
        Setting::set('coins_per_rupee', '50', 'coins');

        $this->assertSame(2.0, coins_to_rupees(100));
        $this->assertSame('₹2.00', format_rupees(coins_to_rupees(100)));
    }

    public function test_invalid_conversion_falls_back_to_100(): void
    {
        Setting::set('coins_per_rupee', '0', 'coins');

        $this->assertSame(1.0, coins_to_rupees(100));
    }

    public function test_format_rupees(): void
    {
        $this->assertSame('₹12.50', format_rupees(12.5));
        $this->assertSame('₹0.00', format_rupees(0));
    }
}
