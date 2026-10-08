<?php

namespace App\Services;

use App\Models\CronRun;
use Cron\CronExpression;

/**
 * Phase 11: the registry of every scheduled job — the scheduler, the
 * admin dashboard and the manual "Run now" action all read from here
 * so nothing is defined twice.
 */
class CronJobRegistry
{
    /**
     * @return list<array{key: string, title: string, description: string, signature: string, expression: string, schedule_label: string}>
     */
    public static function jobs(): array
    {
        return [
            [
                'key' => 'daily-maintenance',
                'title' => 'Daily maintenance',
                'description' => 'Daily bonus window rollover checks, prune old check-in/spin history beyond retention, warm the settings cache.',
                'signature' => 'cron:daily-maintenance',
                'expression' => '0 2 * * *',
                'schedule_label' => 'Daily at 02:00',
            ],
            [
                'key' => 'promotion-cleanup',
                'title' => 'Promotion expiry cleanup',
                'description' => 'Deactivate expired promotions and warn about promotions expiring within 24 hours.',
                'signature' => 'cron:promotion-cleanup',
                'expression' => '0 * * * *',
                'schedule_label' => 'Hourly',
            ],
            [
                'key' => 'payout-queue',
                'title' => 'Payout queue processor',
                'description' => 'Retry stuck payouts through their live drivers; flag rows that exhausted retries for manual review.',
                'signature' => 'cron:payout-queue',
                'expression' => '*/5 * * * *',
                'schedule_label' => 'Every 5 minutes',
            ],
            [
                'key' => 'log-cleanup',
                'title' => 'Log cleanup',
                'description' => 'Prune login attempts, ad impressions, security events, offerwall clicks and postback logs older than the retention setting.',
                'signature' => 'cron:log-cleanup',
                'expression' => '0 3 * * *',
                'schedule_label' => 'Daily at 03:00',
            ],
            [
                'key' => 'session-cleanup',
                'title' => 'Expired session cleanup',
                'description' => 'Delete expired database sessions so the sessions table never grows unbounded.',
                'signature' => 'cron:session-cleanup',
                'expression' => '30 * * * *',
                'schedule_label' => 'Hourly at :30',
            ],
        ];
    }

    /**
     * @return array{key: string, title: string, description: string, signature: string, expression: string, schedule_label: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::jobs() as $job) {
            if ($job['key'] === $key) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Next scheduled run for a cron expression, or null when unparseable.
     */
    public static function nextRun(string $expression): ?\DateTimeImmutable
    {
        try {
            return CronExpression::factory($expression)->getNextRunDate('now', 0, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Dashboard rows: definition + latest run + next run for every job.
     *
     * @return list<array>
     */
    public static function dashboardRows(): array
    {
        $rows = [];

        foreach (self::jobs() as $job) {
            $latest = CronRun::latestFor($job['key']);
            $next = self::nextRun($job['expression']);

            $rows[] = array_merge($job, [
                'latest' => $latest,
                'next_run_at' => $next,
            ]);
        }

        return $rows;
    }

    /**
     * Alert lines for the admin dashboard badge: failed jobs and stale heartbeat.
     *
     * @return list<string>
     */
    public static function alerts(): array
    {
        $alerts = [];

        foreach (self::failedJobKeysWithTitles() as $title) {
            $alerts[] = "Cron job failed: {$title}.";
        }

        $heartbeat = self::heartbeatAt();
        if ($heartbeat === null || $heartbeat->lt(now()->subMinutes(10))) {
            $alerts[] = 'System cron looks stopped — no heartbeat in the last 10 minutes. Add the schedule:run entry shown on the cron page.';
        }

        return $alerts;
    }

    /**
     * @return list<string>
     */
    protected static function failedJobKeysWithTitles(): array
    {
        $titles = [];

        foreach (CronRun::failedJobKeys() as $key) {
            $job = self::find($key);
            $titles[] = $job ? $job['title'] : $key;
        }

        return $titles;
    }

    /**
     * When the system cron last ran (null when it never has).
     */
    public static function heartbeatAt(): ?\Carbon\CarbonImmutable
    {
        $path = storage_path('app/cron_heartbeat.json');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['at'])) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($data['at']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Refresh the heartbeat file — called by the scheduler every minute.
     */
    public static function beat(): void
    {
        file_put_contents(
            storage_path('app/cron_heartbeat.json'),
            json_encode(['at' => now()->toIso8601String()])
        );
    }
}
