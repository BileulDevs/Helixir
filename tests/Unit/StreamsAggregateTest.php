<?php

use App\Services\Collector\StreamsAggregate;

function fakeStream(string $id, int $viewers, string $language = 'en'): array
{
    return ['id' => $id, 'viewer_count' => $viewers, 'language' => $language];
}

it('sums streams and viewers per language', function () {
    $aggregate = new StreamsAggregate(topSize: 2, trackedLanguages: ['fr']);

    $aggregate->add([fakeStream('1', 100), fakeStream('2', 50, 'fr')]);
    $aggregate->add([fakeStream('3', 10, 'fr'), fakeStream('4', 5, '')]);

    expect($aggregate->streamsCount())->toBe(4)
        ->and($aggregate->viewersCount())->toBe(165)
        ->and($aggregate->languages())->toBe([
            'en' => ['streams' => 1, 'viewers' => 100],
            'fr' => ['streams' => 2, 'viewers' => 60],
            'other' => ['streams' => 1, 'viewers' => 5],
        ]);
});

it('counts a stream seen on two pages only once', function () {
    $aggregate = new StreamsAggregate(topSize: 10, trackedLanguages: []);

    $aggregate->add([fakeStream('1', 100), fakeStream('2', 90)]);
    $aggregate->add([fakeStream('2', 90), fakeStream('3', 80)]);

    expect($aggregate->streamsCount())->toBe(3)
        ->and($aggregate->viewersCount())->toBe(270);
});

it('keeps the global top and the top of each tracked language', function () {
    $aggregate = new StreamsAggregate(topSize: 2, trackedLanguages: ['fr']);

    $aggregate->add([
        fakeStream('a', 500), fakeStream('b', 400), fakeStream('c', 300),
        fakeStream('fr1', 30, 'fr'), fakeStream('fr2', 20, 'fr'), fakeStream('fr3', 10, 'fr'),
        fakeStream('de1', 200, 'de'),
    ]);

    expect(array_column($aggregate->topStreams(), 'id'))->toBe(['a', 'b', 'fr1', 'fr2']);
});
