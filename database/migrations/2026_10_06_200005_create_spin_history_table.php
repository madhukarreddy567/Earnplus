<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every spin attempt is logged (won or lost) with the caller's
     * IP and device fingerprint for abuse analysis.
     */
    public function up(): void
    {
        Schema::create('spin_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('result_amount')->default(0);
            $table->boolean('won')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_history');
    }
};
