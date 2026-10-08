<?php

namespace App\Console\Commands;

use App\Models\DailyCheckin;
use App\Models\Setting;
use App\Models\SpinHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11: daily maintenance.
 *
 * Check-in streaks and spin limits are computed from date-stamped rows,
 * so there is no counter to "reset" — this job does the daily rollover
 * work that actually exists:
 *  - prune check-in history older than the retention setting (each
 *    user's latest row is always kept so streaks survive),
 *  - prune spin history older than the retention setting,
 *  - warm the settings cache so the first requests of the day are fast.
 */
class DailyMaintenanceCommand extends BaseCronCommand
{
    protected $signature = 'cron:daily-maintenance';
    protected $description = 'Daily bonus-window rollover, history pruning, settings cache warmup.';

    protected function jobKey(): string
    {
        return 'daily-maintenance';
    }

    protected function executeJob(): array
    {
        $retentionDays = $this->cronSetting('log_retention_days', 90);
        $cutoff = now()->subDays($retentionDays)->toDateString();
        $notes = [];

        // Keep every user's latest check-in (streak math needs it),
        // delete anything older than the retention window.
        $latestIds = DailyCheckin::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        $prunedCheckins = DailyCheckin::whereNotIn('id', $latestIds)
            ->where('checked_in_on', '<', $cutoff)
            ->delete();
        $notes[] = "pruned {$prunedCheckins} old check-in row(s)";

        $prunedSpins = SpinHistory::where('created_at', '<', now()->subDays($retentionDays))->delete();
        $notes[] = "pruned {$prunedSpins} old spin row(s)";

        // Warm the settings cache: preload every key so the day's first
        // requests never hit the DB for configuration.
        $warmed = 0;
        foreach (Setting::all(['key', 'value']) as $setting) {
            Cache::forever("setting.{$setting->key}", $setting->value);
            $warmed++;
        }
        $notes[] = "warmed {$warmed} setting(s)";

        $summary = 'Daily maintenance done: ' . implode(', ', $notes) . '.';

        return ['status' => 'ok', 'summary' => $summary];
    }
}
