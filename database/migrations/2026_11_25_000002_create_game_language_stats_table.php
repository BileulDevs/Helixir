<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_language_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('language', 16);
            $table->unsignedInteger('streams_count');
            $table->unsignedInteger('viewers_count');

            $table->unique(['game_snapshot_id', 'language']);
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_language_stats');
    }
};
