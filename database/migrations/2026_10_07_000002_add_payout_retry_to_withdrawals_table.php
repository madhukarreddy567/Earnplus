<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11: payout-retry bookkeeping on the withdrawals table so the
 * payout-queue cron can retry live drivers and flag stuck rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->unsignedInteger('payout_attempts')->default(0)->after('payout_reference');
            $table->timestamp('next_retry_at')->nullable()->after('payout_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['payout_attempts', 'next_retry_at']);
        });
    }
};
