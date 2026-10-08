<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date-based festival promotions with coin multipliers.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(true);
            $table->decimal('multiplier', 5, 2);
            $table->string('scope', 32);
            $table->foreignId('provider_id')->nullable()
                ->constrained('offerwall_providers')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('banner_title')->nullable();
            $table->string('banner_subtitle')->nullable();
            $table->string('badge_text', 16)->nullable();
            $table->integer('priority')->default(0);
            $table->foreignId('created_by')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'starts_at', 'ends_at']);
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
