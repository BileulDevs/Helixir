<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Répartition d'un snapshot de jeu pour une langue donnée. */
class GameLanguageStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_snapshot_id', 'language', 'streams_count', 'viewers_count'];

    protected function casts(): array
    {
        return [
            'streams_count' => 'integer',
            'viewers_count' => 'integer',
        ];
    }

    /** @return BelongsTo<GameSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GameSnapshot::class, 'game_snapshot_id');
    }
}
