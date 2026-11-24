<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            // Date commune à tous les snapshots du cycle.
            $table->timestamp('collected_at')->unique();
            $table->unsignedInteger('games_count');
            // Rempli quand tous les jobs du cycle sont terminés : l'API ne lit que ces cycles.
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });

        // Les cycles déjà enregistrés (écrits en une transaction) sont complets par construction.
        DB::table('game_snapshots')
            ->select('collected_at')
            ->selectRaw('count(*) as games_count')
            ->groupBy('collected_at')
            ->orderBy('collected_at')
            ->get()
            ->each(fn (object $cycle) => DB::table('collection_runs')->insert([
                'collected_at' => $cycle->collected_at,
                'games_count' => $cycle->games_count,
                'completed_at' => $cycle->collected_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_runs');
    }
};
