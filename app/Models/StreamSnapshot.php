<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un stream du top d'un jeu, tel qu'il était lors d'un snapshot. */
class StreamSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_snapshot_id', 'streamer_id', 'viewers', 'title', 'language', 'started_at'];

    protected function casts(): array
    {
        return [
            'viewers' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Streamer, $this> */
    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }
}
