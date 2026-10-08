<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            AdminSeeder::class,
            OfferwallSeeder::class,
            AdSeeder::class,
            WithdrawalMethodSeeder::class,
            PromotionSeeder::class,
            BrandingSeeder::class,
            PolicyPageSeeder::class,
            LicenseSeeder::class,
        ]);
    }
}
