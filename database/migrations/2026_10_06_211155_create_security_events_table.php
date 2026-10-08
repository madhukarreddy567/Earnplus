<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Security event log (Phase 10): blocked-IP hits, rate-limit hits and
     * other rejected requests. Small rolling log for the admin security page.
     */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // ip_blocked | rate_limited | lockdown
            $table->string('ip', 45)->nullable();
            $table->string('path', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('detail')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
