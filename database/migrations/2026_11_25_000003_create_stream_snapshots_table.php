<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('streamer_id');
            $table->foreign('streamer_id')->references('id')->on('streamers')->cascadeOnDelete();
            $table->unsignedInteger('viewers');
            $table->string('title', 500);
            $table->string('language', 16);
            $table->timestamp('started_at');

            $table->unique(['game_snapshot_id', 'streamer_id']);
            $table->index(['game_snapshot_id', 'viewers']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_snapshots');
    }
};
