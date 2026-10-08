<?php

namespace Tests\Feature\Withdrawals;

use App\Models\Admin;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\WithdrawalMethod;
use App\Services\CoinService;
use App\Services\Withdrawals\WithdrawalService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WithdrawalMethodSeeder;
use Illuminate\Support\Str;

/**
 * Shared builders for withdrawal tests.
 */
trait CreatesWithdrawals
{
    protected WithdrawalService $withdrawals;
    protected CoinService $coins;

    /** @var array<string, string> */
    protected static array $uuidKeys = [];

    /**
     * Deterministic valid UUID per name (the form validates uuid format).
     */
    protected function ukey(string $name): string
    {
        return self::$uuidKeys[$name] ??= (string) Str::uuid();
    }

    protected function setUpCreatesWithdrawals(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(WithdrawalMethodSeeder::class);
        $this->withdrawals = app(WithdrawalService::class);
        $this->coins = app(CoinService::class);
    }

    protected function makeUser(int $coins = 0): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        if ($coins > 0) {
            $this->coins->credit(
                $user,
                $coins,
                CoinTransaction::SOURCE_ADMIN_ADJUST,
                'test-seed-' . $user->id . '-' . $coins
            );
        }

        return $user;
    }

    protected function method(string $type = WithdrawalMethod::TYPE_UPI): WithdrawalMethod
    {
        return WithdrawalMethod::where('type', $type)->firstOrFail();
    }

    protected function upiDetails(): array
    {
        return ['upi_id' => 'testuser@okhdfc'];
    }

    protected function loginAsAdmin(): Admin
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $admin = Admin::where('email', 'admin@earnplus.local')->firstOrFail();
        $this->assertAuthenticatedAs($admin, 'admin');

        return $admin;
    }
}
