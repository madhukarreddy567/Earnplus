<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerwall_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('offerwall_providers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider_tx_id', 128);
            $table->string('click_uid', 64)->nullable();
            $table->unsignedInteger('payout_coins');
            $table->unsignedInteger('user_coins');
            $table->string('status', 16)->default('pending');
            $table->json('raw_payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'provider_tx_id']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerwall_conversions');
    }
};
