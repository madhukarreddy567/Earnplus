<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11: expired session cleanup. Deletes database-driver sessions
 * whose last activity is older than the session lifetime.
 */
class SessionCleanupCommand extends BaseCronCommand
{
    protected $signature = 'cron:session-cleanup';
    protected $description = 'Delete expired sessions from the sessions table.';

    protected function jobKey(): string
    {
        return 'session-cleanup';
    }

    protected function executeJob(): array
    {
        if (! Schema::hasTable('sessions')) {
            return ['status' => 'ok', 'summary' => 'No sessions table (non-database session driver) — nothing to clean.'];
        }

        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetimeMinutes)->timestamp;

        $deleted = DB::table('sessions')->where('last_activity', '<', $cutoff)->delete();

        return ['status' => 'ok', 'summary' => "Deleted {$deleted} expired session(s) (lifetime {$lifetimeMinutes}m)."];
    }
}
