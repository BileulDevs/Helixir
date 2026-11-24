<?php

namespace App\Http\Resources;

use App\Models\GameSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforme un GameSnapshot (avec son jeu) en JSON public.
 * C'est ici qu'on choisit les noms de champs exposés, indépendamment de la base.
 *
 * @mixin GameSnapshot
 */
class GameStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->game->id,
            'name' => $this->game->name,
            'box_art_url' => $this->game->box_art_url,
            'streams' => $this->streams_count,
            'viewers' => $this->viewers_count,
            'average_viewers' => $this->averageViewers(),
            'is_estimate' => $this->is_estimate,
        ];
    }
}
