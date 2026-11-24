<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    // Clé primaire = id Twitch : une chaîne, jamais auto-incrémentée.
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'box_art_url'];

    /** @return HasMany<GameSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(GameSnapshot::class);
    }

    /** Le snapshot le plus récent du jeu, parmi les cycles de collecte terminés. */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(GameSnapshot::class)->ofMany(
            ['collected_at' => 'max'],
            fn ($query) => $query->whereIn(
                'game_snapshots.collected_at',
                CollectionRun::query()->completed()->select('collected_at'),
            ),
        );
    }
}
