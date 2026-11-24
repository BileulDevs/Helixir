<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('game_id');
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->timestamp('collected_at');
            $table->unsignedInteger('streams_count');
            $table->unsignedInteger('viewers_count');
            $table->boolean('is_estimate')->default(false);

            $table->index(['game_id', 'collected_at']);
            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_snapshots');
    }
};
