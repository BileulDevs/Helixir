<?php

use App\Models\GameSnapshot;

it('computes the average viewers per stream', function (int $streams, int $viewers, int $expected) {
    $snapshot = new GameSnapshot(['streams_count' => $streams, 'viewers_count' => $viewers]);

    expect($snapshot->averageViewers())->toBe($expected);
})->with([
    'exact division' => [10, 1000, 100],
    'rounded' => [3, 10, 3],
    'no stream' => [0, 0, 0],
]);
