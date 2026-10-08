<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rich-text policy pages (terms, privacy, refund, about) with
     * full version history so bad edits can be rolled back.
     */
    public function up(): void
    {
        Schema::create('policy_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body_html')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('policy_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_page_id')->constrained('policy_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body_html');
            $table->boolean('was_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_page_revisions');
        Schema::dropIfExists('policy_pages');
    }
};
