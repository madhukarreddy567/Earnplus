<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13: license/domain-lock violation log.
 *
 * Types: domain_mismatch, signature_tamper, kill_switch, domain_unconfigured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_violations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('ip', 45)->nullable();
            $table->string('host', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_violations');
    }
};
