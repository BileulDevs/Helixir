<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HealthController extends Controller
{
    /** Au-delà de ce délai sans collecte, les données sont jugées périmées. */
    private const STALE_AFTER_MINUTES = 10;

    /**
     * État de l'API.
     *
     * Public, sans clé. `status` vaut `stale` si aucun snapshot n'a été collecté depuis 10 minutes.
     */
    public function __invoke(): JsonResponse
    {
        $latest = GameSnapshot::latestCollectedAt();
        $lastSnapshotAt = $latest !== null ? Carbon::parse($latest) : null;

        $isFresh = $lastSnapshotAt !== null
            && $lastSnapshotAt->greaterThan(now()->subMinutes(self::STALE_AFTER_MINUTES));

        return response()->json([
            'data' => [
                'status' => $isFresh ? 'ok' : 'stale',
                'last_snapshot_at' => $lastSnapshotAt?->toIso8601ZuluString(),
            ],
        ]);
    }
}
