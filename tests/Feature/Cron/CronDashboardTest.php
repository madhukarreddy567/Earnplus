<?php

namespace Tests\Feature\Cron;

use App\Models\Admin;
use App\Models\CronRun;
use App\Services\CronJobRegistry;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 11: the admin cron dashboard — auth gates, job list, run-now,
 * heartbeat display and failure badge.
 */
class CronDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);
    }

    /** @test */
    public function guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin/crons')->assertRedirect(route('admin.login'));
        $this->post('/admin/crons/payout-queue/run')->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function the_dashboard_lists_every_registered_job(): void
    {
        $this->loginAsAdmin();

        $response = $this->get('/admin/crons')->assertOk();

        foreach (CronJobRegistry::jobs() as $job) {
            $response->assertSee($job['title']);
            $response->assertSee($job['schedule_label']);
        }

        $response->assertSee('System cron');
        $response->assertSee('Run history');
    }

    /** @test */
    public function a_completed_run_shows_on_the_dashboard(): void
    {
        $this->loginAsAdmin();

        $this->artisan('cron:session-cleanup')->assertSuccessful();

        $this->get('/admin/crons')
            ->assertOk()
            ->assertSee('Expired session cleanup')
            ->assertSee('ok');
    }

    /** @test */
    public function run_now_triggers_the_job_and_records_the_run(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/admin/crons/session-cleanup/run');

        $response->assertRedirect(route('admin.crons.index'));
        $response->assertSessionHas('success');

        $run = CronRun::latestFor('session-cleanup');
        $this->assertNotNull($run);
        $this->assertSame(CronRun::STATUS_OK, $run->status);
    }

    /** @test */
    public function run_now_for_an_unknown_job_404s(): void
    {
        $this->loginAsAdmin();

        $this->post('/admin/crons/nope-not-real/run')->assertNotFound();
    }

    /** @test */
    public function run_now_refuses_when_the_job_is_already_running(): void
    {
        $this->loginAsAdmin();

        \Illuminate\Support\Facades\Cache::lock('cron:lock:session-cleanup', 60)->acquire();

        $response = $this->post('/admin/crons/session-cleanup/run');

        $response->assertRedirect(route('admin.crons.index'));
        $response->assertSessionHas('error');
        $this->assertNull(CronRun::latestFor('session-cleanup'));
    }

    /** @test */
    public function a_stale_heartbeat_shows_the_setup_snippet(): void
    {
        $this->loginAsAdmin();

        @unlink(storage_path('app/cron_heartbeat.json'));

        $this->get('/admin/crons')
            ->assertOk()
            ->assertSee('looks stopped')
            ->assertSee('schedule:run');
    }

    /** @test */
    public function a_fresh_heartbeat_shows_alive(): void
    {
        $this->loginAsAdmin();

        CronJobRegistry::beat();

        $this->get('/admin/crons')
            ->assertOk()
            ->assertSee('is running');
    }

    /** @test */
    public function the_admin_dashboard_badges_failed_jobs(): void
    {
        $this->loginAsAdmin();

        CronRun::start('payout-queue')->fail('boom');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Background jobs need attention')
            ->assertSee('Cron job failed');
    }

    /** @test */
    public function the_admin_dashboard_badges_a_stopped_system_cron(): void
    {
        $this->loginAsAdmin();

        @unlink(storage_path('app/cron_heartbeat.json'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('System cron looks stopped');
    }
}
