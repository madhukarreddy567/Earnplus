<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerwall_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('postback_secret', 128);
            $table->text('ip_whitelist')->nullable();
            $table->decimal('user_revenue_share', 5, 2)->default(70.00);
            $table->json('config')->nullable();
            $table->boolean('sandbox_mode')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerwall_providers');
    }
};
