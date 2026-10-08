<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending spin-reward claims gated behind a verified Unity rewarded ad.
 *
 * POST /api/spin decides the outcome server-side but does NOT credit —
 * it mints a single-use, expiring claim token. The reward is credited
 * only by POST /api/spin/claim after the server receives Unity's
 * signed server-to-server callback for that exact token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spin_history_id')->constrained('spin_history')->cascadeOnDelete()->unique();
            $table->unsignedInteger('amount');
            $table->string('token', 64)->unique();
            $table->string('status', 16)->default('pending'); // pending|verified|claimed|expired
            $table->string('unity_sid', 128)->nullable();
            $table->timestamp('unity_verified_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_claims');
    }
};
