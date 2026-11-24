<?php

namespace App\Services\Twitch;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Seul point de contact avec l'API Twitch Helix.
 * Gère le token d'application, les retries et les limites de débit.
 */
class TwitchClient
{
    private const TOKEN_CACHE_KEY = 'twitch:app_token';

    private const PAGE_SIZE = 100;

    private const RETRIES = 3;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $authUrl,
        private readonly string $apiUrl,
        private readonly int $rateLimitReserve,
    ) {}

    /** Construit le client depuis config/helixir.php, ou échoue clairement si les clés manquent. */
    public static function fromConfig(): self
    {
        $config = config('helixir.twitch');

        if (blank($config['client_id']) || blank($config['client_secret'])) {
            throw new RuntimeException('TWITCH_CLIENT_ID and TWITCH_CLIENT_SECRET must be set.');
        }

        return new self(
            $config['client_id'],
            $config['client_secret'],
            $config['auth_url'],
            $config['api_url'],
            $config['rate_limit_reserve'],
        );
    }

    /**
     * Les jeux les plus regardés, dans l'ordre de Twitch.
     *
     * @return list<array{id: string, name: string, box_art_url: string}>
     */
    public function topGames(int $limit): array
    {
        $games = [];
        $cursor = null;

        do {
            $page = $this->get('games/top', array_filter([
                'first' => min(self::PAGE_SIZE, $limit - count($games)),
                'after' => $cursor,
            ]));

            foreach ($page['data'] as $game) {
                // Twitch renvoie parfois des jeux sans identifiant : on les ignore.
                if (($game['id'] ?? '') !== '') {
                    $games[] = $game;
                }
            }

            $cursor = $page['pagination']['cursor'] ?? null;
        } while ($cursor !== null && count($games) < $limit);

        return array_slice($games, 0, $limit);
    }

    /**
     * Une page de streams en direct pour un jeu, triée par spectateurs.
     *
     * @return array{data: list<array<string, mixed>>, cursor: ?string}
     */
    public function streamsPage(string $gameId, ?string $after = null): array
    {
        $page = $this->get('streams', array_filter([
            'game_id' => $gameId,
            'first' => self::PAGE_SIZE,
            'after' => $after,
        ]));

        return [
            'data' => $page['data'],
            'cursor' => $page['pagination']['cursor'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query): array
    {
        $response = $this->send($path, $query);

        // Token expiré ou révoqué : on en redemande un, une seule fois.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($path, $query);
        }

        $response->throw();
        $this->respectRateLimit($response);

        return $response->json();
    }

    private function send(string $path, array $query): Response
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($this->token())
            ->withHeaders(['Client-Id' => $this->clientId])
            ->acceptJson()
            ->retry(
                self::RETRIES,
                // Délai exponentiel : 500 ms, 1 s, 2 s.
                fn (int $attempt) => 250 * (2 ** $attempt),
                fn (\Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                throw: false,
            )
            ->get($path, $query);
    }

    /** Token d'application (flux client credentials), gardé en cache jusqu'à son expiration. */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::asForm()->post($this->authUrl, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ])->throw();

        $token = $response->json('access_token');
        // Marge d'une minute pour ne jamais envoyer un token sur le point d'expirer.
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $response->json('expires_in') - 60));

        return $token;
    }

    /** Ralentit avant d'atteindre la limite de débit indiquée par Twitch. */
    private function respectRateLimit(Response $response): void
    {
        $remaining = $response->header('Ratelimit-Remaining');
        $reset = $response->header('Ratelimit-Reset');

        if ($remaining === '' || $reset === '' || (int) $remaining > $this->rateLimitReserve) {
            return;
        }

        $resetAt = Carbon::createFromTimestamp((int) $reset);

        if ($resetAt->isFuture()) {
            Sleep::until($resetAt);
        }
    }
}
