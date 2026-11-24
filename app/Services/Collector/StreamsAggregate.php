<?php

namespace App\Services\Collector;

/**
 * Additionne les pages de streams d'un jeu : totaux, répartition par langue et tops.
 * Classe sans base de données ni HTTP, pour être testée unitairement.
 */
final class StreamsAggregate
{
    private int $streams = 0;

    private int $viewers = 0;

    /** @var array<string, array{streams: int, viewers: int}> */
    private array $languages = [];

    /** @var array<string, true> Identifiants déjà comptés. */
    private array $seen = [];

    /** @var list<array<string, mixed>> */
    private array $top = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $topByLanguage = [];

    /**
     * @param  list<string>  $trackedLanguages
     */
    public function __construct(
        private readonly int $topSize,
        private readonly array $trackedLanguages,
    ) {}

    /** @param list<array<string, mixed>> $streams Une page renvoyée par GET /streams. */
    public function add(array $streams): void
    {
        foreach ($streams as $stream) {
            // Pendant la pagination, un stream peut changer de page : on ne le compte qu'une fois.
            if (isset($this->seen[$stream['id']])) {
                continue;
            }
            $this->seen[$stream['id']] = true;

            $viewers = (int) $stream['viewer_count'];
            $language = $stream['language'] !== '' ? $stream['language'] : 'other';

            $this->streams++;
            $this->viewers += $viewers;
            $this->languages[$language]['streams'] = ($this->languages[$language]['streams'] ?? 0) + 1;
            $this->languages[$language]['viewers'] = ($this->languages[$language]['viewers'] ?? 0) + $viewers;

            $this->top = $this->pushTop($this->top, $stream);

            if (in_array($language, $this->trackedLanguages, true)) {
                $this->topByLanguage[$language] = $this->pushTop($this->topByLanguage[$language] ?? [], $stream);
            }
        }
    }

    public function streamsCount(): int
    {
        return $this->streams;
    }

    public function viewersCount(): int
    {
        return $this->viewers;
    }

    /** @return array<string, array{streams: int, viewers: int}> */
    public function languages(): array
    {
        return $this->languages;
    }

    /**
     * Union du top global et des tops par langue suivie, sans doublon.
     *
     * @return list<array<string, mixed>>
     */
    public function topStreams(): array
    {
        $byId = [];

        foreach ([$this->top, ...array_values($this->topByLanguage)] as $list) {
            foreach ($list as $stream) {
                $byId[$stream['id']] = $stream;
            }
        }

        return array_values($byId);
    }

    /**
     * @param  list<array<string, mixed>>  $top
     * @param  array<string, mixed>  $stream
     * @return list<array<string, mixed>>
     */
    private function pushTop(array $top, array $stream): array
    {
        $top[] = $stream;
        usort($top, fn (array $a, array $b) => $b['viewer_count'] <=> $a['viewer_count']);

        return array_slice($top, 0, $this->topSize);
    }
}
