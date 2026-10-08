<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11: one row per cron-job execution. The admin cron dashboard
 * and the dashboard alert badge read the latest row per job key.
 */
class CronRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'job_key', 'status', 'summary', 'output',
        'started_at', 'finished_at', 'duration_ms',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Open a run row (status=running) and return it.
     */
    public static function start(string $jobKey): static
    {
        return static::create([
            'job_key' => $jobKey,
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Close the run with a final status, summary and optional output.
     */
    public function finish(string $status, ?string $summary = null, ?string $output = null): void
    {
        $finished = now();

        $this->update([
            'status' => $status,
            'summary' => $summary !== null ? mb_substr($summary, 0, 500) : null,
            'output' => $output,
            'finished_at' => $finished,
            'duration_ms' => $this->started_at
                ? (int) $this->started_at->diffInMilliseconds($finished)
                : null,
        ]);
    }

    /**
     * Close the run as failed with the exception message.
     */
    public function fail(string $message): void
    {
        $this->finish(self::STATUS_FAILED, mb_substr($message, 0, 500), $message);
    }

    /**
     * The latest finished (non-running) run for a job key, if any.
     */
    public static function latestFor(string $jobKey): ?static
    {
        return static::where('job_key', $jobKey)
            ->where('status', '!=', self::STATUS_RUNNING)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Job keys whose latest finished run failed (used for the dashboard badge).
     *
     * @return list<string>
     */
    public static function failedJobKeys(): array
    {
        return static::query()
            ->select('job_key')
            ->where('status', self::STATUS_FAILED)
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('cron_runs')
                    ->where('status', '!=', self::STATUS_RUNNING)
                    ->groupBy('job_key');
            })
            ->pluck('job_key')
            ->all();
    }
}
