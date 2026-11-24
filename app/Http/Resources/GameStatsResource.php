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
            /** Nombre de streams en direct, tous comptés un par un. */
            'streams' => $this->streams_count,
            /**
             * Somme des compteurs de spectateurs publics de chaque stream (API Twitch Helix).
             * Environ 10 % sous le total affiché sur le site Twitch, calculé autrement par Twitch.
             */
            'viewers' => $this->viewers_count,
            /** Spectateurs par stream, arrondi. */
            'average_viewers' => $this->averageViewers(),
            /** `true` si la lecture des streams n'a pas pu être complète (garde-fou atteint). */
            'is_estimate' => $this->is_estimate,
        ];
    }
}
