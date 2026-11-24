<?php

namespace App\Models;

use Database\Factories\GameSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSnapshot extends Model
{
    /** @use HasFactory<GameSnapshotFactory> */
    use HasFactory;

    // La table n'a pas de created_at / updated_at : collected_at suffit.
    public $timestamps = false;

    protected $fillable = ['game_id', 'collected_at', 'streams_count', 'viewers_count', 'is_estimate'];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'streams_count' => 'integer',
            'viewers_count' => 'integer',
            'is_estimate' => 'boolean',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<GameLanguageStat, $this> */
    public function languageStats(): HasMany
    {
        return $this->hasMany(GameLanguageStat::class);
    }

    /** @return HasMany<StreamSnapshot, $this> */
    public function streams(): HasMany
    {
        return $this->hasMany(StreamSnapshot::class);
    }

    /** Moyenne de spectateurs par stream, arrondie (0 si aucun stream). */
    public function averageViewers(): int
    {
        return $this->streams_count > 0
            ? (int) round($this->viewers_count / $this->streams_count)
            : 0;
    }

    /** Date du dernier cycle de collecte, ou null si la base est vide. */
    public static function latestCollectedAt(): ?string
    {
        return static::query()->max('collected_at');
    }

    /** Restreint la requête aux snapshots d'un cycle donné. */
    #[Scope]
    protected function collectedAt(Builder $query, string $collectedAt): void
    {
        // Colonne qualifiée (game_snapshots.collected_at) pour rester sans ambiguïté en cas de jointure.
        $query->where($query->qualifyColumn('collected_at'), $collectedAt);
    }
}
