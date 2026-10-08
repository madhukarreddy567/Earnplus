<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per user per calendar day they checked in.
     * Streak = consecutive days, stored on the latest row.
     */
    public function up(): void
    {
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checked_in_on');
            $table->unsignedInteger('streak')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'checked_in_on']);
            $table->index('checked_in_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
    }
};
