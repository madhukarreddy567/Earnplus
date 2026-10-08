<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\CronJobRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11: every cron command extends this. It records the run in
 * `cron_runs` (ok / warning / failed), captures exceptions as failed
 * runs, and takes a named lock so scheduler runs and manual "Run now"
 * clicks can never overlap.
 */
abstract class BaseCronCommand extends Command
{
    /**
     * The registry key for this job (e.g. 'payout-queue').
     */
    abstract protected function jobKey(): string;

    /**
     * Do the work. Return ['status' => 'ok'|'warning', 'summary' => string, 'output' => ?string].
     */
    abstract protected function executeJob(): array;

    public function handle(): int
    {
        $lock = Cache::lock("cron:lock:{$this->jobKey()}", 600);

        if (! $lock->get()) {
            $this->error('Another run of this job is already in progress.');
            CronRun::start($this->jobKey())->fail('Skipped: another run was already in progress.');

            return self::FAILURE;
        }

        $run = CronRun::start($this->jobKey());

        try {
            $result = $this->executeJob();

            $status = $result['status'] === CronRun::STATUS_WARNING
                ? CronRun::STATUS_WARNING
                : CronRun::STATUS_OK;

            $run->finish($status, $result['summary'] ?? null, $result['output'] ?? null);
            $this->info($result['summary'] ?? 'Done.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->fail($e->getMessage());
            report($e);
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Read an integer cron setting with a sane floor.
     */
    protected function cronSetting(string $key, int $default, int $min = 1): int
    {
        return max($min, setting_int($key, $default));
    }
}
