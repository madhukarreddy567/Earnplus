<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-to-server rewards (AdMob SSV / Unity S2S) have no web
     * placement — allow a null placement_id on the reward log.
     * (Raw SQL: doctrine/dbal is not installed for ->change().)
     */
    public function up(): void
    {
        Schema::table('ad_rewards', function ($table) {
            $table->dropForeign(['placement_id']);
        });

        DB::statement('ALTER TABLE ad_rewards MODIFY placement_id BIGINT UNSIGNED NULL');

        Schema::table('ad_rewards', function ($table) {
            $table->foreign('placement_id')
                ->references('id')->on('ad_placements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('DELETE FROM ad_rewards WHERE placement_id IS NULL');

        Schema::table('ad_rewards', function ($table) {
            $table->dropForeign(['placement_id']);
        });

        DB::statement('ALTER TABLE ad_rewards MODIFY placement_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ad_rewards', function ($table) {
            $table->foreign('placement_id')
                ->references('id')->on('ad_placements')
                ->cascadeOnDelete();
        });
    }
};
