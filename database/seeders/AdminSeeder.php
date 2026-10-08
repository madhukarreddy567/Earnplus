<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default Super Admin account.
     *
     * Credentials come from the environment — never hardcode a real
     * password here. Defaults are documented in the README and must
     * be changed immediately after first login.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@earnplus.local')],
            [
                'name' => 'Super Admin',
                'password' => env('ADMIN_SEED_PASSWORD', 'ChangeMe123!'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
