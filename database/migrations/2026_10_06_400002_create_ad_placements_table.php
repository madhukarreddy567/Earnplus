<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('ad_networks')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('slot', 60)->index();
            $table->boolean('enabled')->default(true);
            $table->string('placement_type', 30)->default('banner');
            $table->string('device', 20)->default('all');
            $table->json('pages')->nullable();
            $table->unsignedInteger('frequency_cap_per_session')->default(3);
            $table->unsignedInteger('priority')->default(10);
            $table->unsignedInteger('coins')->default(0);
            $table->text('custom_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
