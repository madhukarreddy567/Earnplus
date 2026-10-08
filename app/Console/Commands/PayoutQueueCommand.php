<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\PayoutQueueService;

/**
 * Phase 11: payout queue processor. Retries stuck payouts through
 * their live drivers and flags rows that exhausted retries.
 */
class PayoutQueueCommand extends BaseCronCommand
{
    protected $signature = 'cron:payout-queue';
    protected $description = 'Retry stuck payouts through live drivers; flag exhausted rows for manual review.';

    protected function jobKey(): string
    {
        return 'payout-queue';
    }

    protected function executeJob(): array
    {
        $result = app(PayoutQueueService::class)->process();

        $summary = sprintf(
            'Payout queue: %d retried, %d completed, %d failed this run.',
            $result['retried'],
            $result['completed'],
            $result['failed']
        );

        if ($result['warnings'] !== []) {
            return [
                'status' => CronRun::STATUS_WARNING,
                'summary' => $summary . ' ' . implode(' ', $result['warnings']),
            ];
        }

        return ['status' => CronRun::STATUS_OK, 'summary' => $summary];
    }
}
