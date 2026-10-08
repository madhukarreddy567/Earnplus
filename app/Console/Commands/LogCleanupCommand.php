<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11: log cleanup. Prunes operational logs older than the
 * `log_retention_days` setting (default 90). Money records — coin
 * transactions, offerwall conversions, withdrawals — are NEVER pruned.
 */
class LogCleanupCommand extends BaseCronCommand
{
    protected $signature = 'cron:log-cleanup';
    protected $description = 'Prune old login attempts, impressions, security events, clicks and cron runs.';

    protected function jobKey(): string
    {
        return 'log-cleanup';
    }

    protected function executeJob(): array
    {
        $retentionDays = $this->cronSetting('log_retention_days', 90);
        $cutoff = now()->subDays($retentionDays);
        $notes = [];

        $tables = [
            'login_logs' => 'created_at',
            'ad_impressions' => 'created_at',
            'security_events' => 'created_at',
            'offerwall_clicks' => 'created_at',
        ];

        foreach ($tables as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $deleted = DB::table($table)->where($column, '<', $cutoff)->delete();
            $notes[] = "{$table}: {$deleted}";
        }

        // Keep cron run history lean too — but always keep the latest
        // run of every job so the dashboard never goes blank.
        $latestIds = CronRun::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('job_key')
            ->pluck('id');

        $deletedRuns = CronRun::whereNotIn('id', $latestIds)
            ->where('created_at', '<', $cutoff)
            ->delete();
        $notes[] = "cron_runs: {$deletedRuns}";

        $summary = "Log cleanup (retention {$retentionDays}d) — deleted " . implode(', ', $notes) . '.';

        return ['status' => 'ok', 'summary' => $summary];
    }
}
