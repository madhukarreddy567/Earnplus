<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only coin ledger. Every coin movement in the app —
     * bonuses, spins, tasks, admin adjustments, withdrawals —
     * is exactly one row here. Balance is derived, never edited directly.
     */
    public function up(): void
    {
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // credit | debit
            $table->bigInteger('amount');
            $table->string('source', 40); // signup_bonus, referral_bonus, daily_checkin, spin, task_offerwall, rewarded_ad, admin_adjust, withdrawal
            $table->string('reference', 100)->nullable(); // e.g. admin id, offerwall click id
            $table->json('meta')->nullable();
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 120)->unique()->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
