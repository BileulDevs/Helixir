<?php

namespace App\Http\Resources;

use App\Models\StreamSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Top des streams d'un snapshot. Le rang dépend de la position dans la liste,
 * il est donc calculé ici plutôt que dans chaque élément.
 */
class TopStreamerCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection
            ->values()
            ->map(fn (StreamSnapshot $stream, int $index) => [
                'rank' => $index + 1,
                'login' => $stream->streamer->login,
                'display_name' => $stream->streamer->display_name,
                'viewers' => $stream->viewers,
                'title' => $stream->title,
                'language' => $stream->language,
                'started_at' => $stream->started_at->toIso8601ZuluString(),
            ])
            ->all();
    }
}
