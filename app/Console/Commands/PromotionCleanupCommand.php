<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Models\Promotion;

/**
 * Phase 11: promotion expiry cleanup. Deactivates promotions whose
 * window has ended (logged in the run output) and raises a warning
 * for promotions expiring within the next 24 hours.
 */
class PromotionCleanupCommand extends BaseCronCommand
{
    protected $signature = 'cron:promotion-cleanup';
    protected $description = 'Deactivate expired promotions; warn about ones expiring within 24h.';

    protected function jobKey(): string
    {
        return 'promotion-cleanup';
    }

    protected function executeJob(): array
    {
        $now = now();
        $output = [];

        $expired = Promotion::where('enabled', true)
            ->where('ends_at', '<', $now)
            ->get();

        foreach ($expired as $promotion) {
            $promotion->update(['enabled' => false]);
            $output[] = "deactivated '{$promotion->name}' (ended {$promotion->ends_at->toDateTimeString()})";
        }

        $expiringSoon = Promotion::where('enabled', true)
            ->where('ends_at', '>=', $now)
            ->where('ends_at', '<', $now->copy()->addDay())
            ->orderBy('ends_at')
            ->get();

        $warnings = [];
        foreach ($expiringSoon as $promotion) {
            $warnings[] = "'{$promotion->name}' expires at {$promotion->ends_at->toDateTimeString()}";
        }

        $summary = count($expired) === 0
            ? 'No expired promotions to deactivate.'
            : 'Deactivated ' . count($expired) . ' expired promotion(s).';

        if ($warnings !== []) {
            return [
                'status' => CronRun::STATUS_WARNING,
                'summary' => $summary . ' Expiring within 24h: ' . implode('; ', $warnings) . '.',
                'output' => implode("\n", array_merge($output, ['WARNINGS:', ...$warnings])),
            ];
        }

        return [
            'status' => CronRun::STATUS_OK,
            'summary' => $summary,
            'output' => $output === [] ? null : implode("\n", $output),
        ];
    }
}
