<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un jeu du référentiel, sans statistiques (utilisé par la recherche).
 *
 * @mixin Game
 */
class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'box_art_url' => $this->box_art_url,
        ];
    }
}
