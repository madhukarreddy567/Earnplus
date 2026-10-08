<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Str;

/**
 * Shared promotion test helpers: make promotions, users, admins.
 */
trait CreatesPromotions
{
    protected function setUpCreatesPromotions(): void
    {
        $this->seed(SettingSeeder::class);
    }

    protected function makePromotion(array $overrides = []): Promotion
    {
        $name = $overrides['name'] ?? 'Test Promo ' . Str::random(6);

        return Promotion::create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'enabled' => true,
            'multiplier' => 2.00,
            'scope' => Promotion::SCOPE_GLOBAL,
            'provider_id' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'banner_title' => 'Test promo banner',
            'banner_subtitle' => 'Double coins for a limited time',
            'badge_text' => '2X',
            'priority' => 0,
        ], $overrides));
    }

    protected function verifiedUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticatedAs(
            \App\Models\Admin::where('email', 'admin@earnplus.local')->first(),
            'admin'
        );
    }
}
