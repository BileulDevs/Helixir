<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un cycle de collecte. Ses jeux sont collectés en parallèle ; le cycle n'est visible
 * dans l'API qu'une fois tous ses jobs terminés (completed_at rempli).
 */
class CollectionRun extends Model
{
    protected $fillable = ['collected_at', 'games_count', 'completed_at'];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'games_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    #[Scope]
    protected function inProgress(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** Date du dernier cycle complet, ou null si aucun cycle n'est terminé. */
    public static function latestCompletedAt(): ?string
    {
        return static::query()->completed()->max('collected_at');
    }

    public function markCompleted(): void
    {
        $this->forceFill(['completed_at' => now()])->save();
    }
}
