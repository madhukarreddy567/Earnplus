<?php

namespace Tests\Feature\AdminPanel;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 9 authorization audit: EVERY admin route must sit behind the
 * admin guard — guests get bounced to the admin login, and plain
 * (web-guard) users can never reach them either.
 */
class AdminAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * All named admin routes (excluding the login/logout endpoints).
     *
     * @return list<\Illuminate\Routing\Route>
     */
    protected function adminRoutes(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.'))
            // Login form/store and logout are intentionally public to guests.
            ->filter(fn ($route) => ! in_array($route->getName(), ['admin.login', 'admin.', 'admin.logout'], true))
            ->values()
            ->all();
    }

    /** @test */
    public function every_admin_route_carries_the_admin_auth_middleware(): void
    {
        $missing = [];

        foreach ($this->adminRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $hasAuthAdmin = collect($middleware)->contains(
                fn ($m) => $m === 'auth:admin' || (is_string($m) && str_contains($m, 'Authenticate'))
            );

            if (! $hasAuthAdmin) {
                $missing[] = $route->getName() . ' [' . implode(',', $route->methods()) . ']';
            }
        }

        $this->assertSame([], $missing, 'Admin routes missing auth:admin: ' . implode(', ', $missing));
    }

    /** @test */
    public function guests_are_bounced_from_every_admin_get_route(): void
    {
        $failures = [];

        foreach ($this->adminRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $this->sampleUri($route);

            if ($uri === null) {
                continue;
            }

            $response = $this->get($uri);

            if (! $response->isRedirect(route('admin.login'))) {
                $failures[] = $route->getName() . " ({$uri}) => {$response->status()}";
            }
        }

        $this->assertSame([], $failures, 'Admin GET routes reachable by guests: ' . implode(', ', $failures));
    }

    /** @test */
    public function web_guard_users_cannot_reach_admin_routes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // Spot-check the highest-value endpoints.
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
        $this->get('/admin/wallets')->assertRedirect(route('admin.login'));
        $this->get('/admin/withdrawals')->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function role_gated_routes_forbid_non_super_admins(): void
    {
        $this->seed(AdminSeeder::class);

        // AdminSeeder creates the super admin; make a plain admin.
        $admin = \App\Models\Admin::create([
            'name' => 'Staff',
            'email' => 'staff@earnplus.local',
            'password' => 'ChangeMe123!',
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'admin');

        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/settings')->assertOk();
    }

    /**
     * Fill route parameters with plausible samples so the route resolves.
     */
    protected function sampleUri(\Illuminate\Routing\Route $route): ?string
    {
        $uri = $route->uri();
        $samples = [
            'provider' => 'demo',
            'conversion' => '1',
            'network' => '1',
            'placement' => '1',
            'withdrawal' => '1',
            'method' => '1',
            'promotion' => '1',
            'policy' => '1',
            'revision' => '1',
            'user' => '1',
            'type' => 'logo',
        ];

        foreach ($route->parameterNames() as $param) {
            if (! isset($samples[$param])) {
                return null;
            }

            $uri = str_replace('{' . $param . '}', $samples[$param], $uri);
            $uri = str_replace('{' . $param . '?}', $samples[$param], $uri);
        }

        return '/' . $uri;
    }
}
