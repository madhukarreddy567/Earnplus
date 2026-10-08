<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11: run history for every scheduled cron job — the admin
 * dashboard reads the latest row per job for status/last-run display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_key', 64)->index();
            $table->enum('status', ['running', 'ok', 'warning', 'failed'])->default('running')->index();
            $table->string('summary', 500)->nullable();
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_runs');
    }
};
