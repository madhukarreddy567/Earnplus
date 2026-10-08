<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerwall_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('offerwall_providers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('click_uid', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->string('status', 16)->default('clicked');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['provider_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerwall_clicks');
    }
};
