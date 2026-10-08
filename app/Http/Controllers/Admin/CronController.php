<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronRun;
use App\Services\CronJobRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Phase 11: the admin cron dashboard — every job's schedule, last run,
 * status, next run, run history, heartbeat health, and manual "Run now".
 */
class CronController extends Controller
{
    public function index(): View
    {
        $heartbeatAt = CronJobRegistry::heartbeatAt();
        $heartbeatAlive = $heartbeatAt !== null && $heartbeatAt->gt(now()->subMinutes(10));

        return view('admin.crons.index', [
            'rows' => CronJobRegistry::dashboardRows(),
            'heartbeatAt' => $heartbeatAt,
            'heartbeatAlive' => $heartbeatAlive,
            'history' => CronRun::orderByDesc('id')->limit(50)->get(),
            'setupSnippet' => '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1',
        ]);
    }

    /**
     * Manual "Run now" fallback for when the system cron is unavailable
     * (or for an impatient admin). Never overlaps a running job.
     */
    public function run(string $job): RedirectResponse
    {
        $definition = CronJobRegistry::find($job);

        if ($definition === null) {
            abort(404);
        }

        $lock = Cache::lock("cron:lock:{$job}", 10);

        if (! $lock->get()) {
            return redirect()->route('admin.crons.index')
                ->with('error', "“{$definition['title']}” is already running — try again in a moment.");
        }

        $lock->release();

        Artisan::call($definition['signature']);

        $latest = CronRun::latestFor($job);
        $status = $latest?->status ?? 'unknown';

        return redirect()->route('admin.crons.index')->with(
            $status === CronRun::STATUS_FAILED ? 'error' : 'success',
            "“{$definition['title']}” finished with status: {$status}."
                . ($latest?->summary ? " {$latest->summary}" : '')
        );
    }
}
