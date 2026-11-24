# Helixir

API REST qui transforme les données en direct de Twitch en statistiques par jeu : nombre de streams, spectateurs, moyenne par stream, répartition par langue et top des streamers.

L'API Twitch (Helix) ne donne pas le nombre de streams d'un jeu ni d'historique. Helixir les calcule en parcourant les streams de chaque jeu toutes les 5 minutes et conserve chaque mesure en base.

> Données fournies par l'API Twitch. Projet indépendant, non affilié à Twitch.

## Architecture

```
Twitch Helix ──(toutes les 5 min)──> Collecteur ──> PostgreSQL ──> API /api/v1/...
                                    (scheduler)                    (lit uniquement la base,
                                                                    réponses en cache 60 s)
```

L'API ne contacte jamais Twitch pendant une requête : elle reste rapide et ne dépend pas des limites de débit de Twitch.

| Conteneur | Rôle |
|---|---|
| `app` | API Laravel sur le port 8000, applique les migrations au démarrage |
| `scheduler` | Lance `helixir:collect` toutes les 5 minutes |
| `db` | PostgreSQL 17 |

## Démarrage

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan helixir:api-key:create "mon poste"
```

La dernière commande affiche une clé `hx_...` une seule fois : garde-la.

Sans clé Twitch, l'API fonctionne avec des données de démonstration (`SEED_DEMO_DATA=true` par défaut).

### Brancher Twitch

1. Crée une application sur [dev.twitch.tv/console](https://dev.twitch.tv/console).
2. Renseigne `TWITCH_CLIENT_ID` et `TWITCH_CLIENT_SECRET` dans `.env` (jamais commité).
3. Lance une collecte : `docker compose exec app php artisan helixir:collect`. Le conteneur `scheduler` prend ensuite le relais toutes les 5 minutes.

Pour ne garder que les vraies données, mets `SEED_DEMO_DATA=false` et repars d'une base vide : `docker compose exec app php artisan migrate:fresh --force`.

## Endpoints

Toutes les routes sauf `/health` exigent `Authorization: Bearer <clé>`. Exemples prêts à l'emploi dans [`api.http`](api.http).

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/v1/health` | État de l'API et date du dernier snapshot (public) |
| GET | `/api/v1/games` | Jeux du dernier cycle. Paramètres : `limit` (1-100), `page`, `sort` (`viewers`, `streams`), `language` |
| GET | `/api/v1/games/search?q=` | Recherche par nom, insensible à la casse |
| GET | `/api/v1/games/{id}` | Statistiques actuelles d'un jeu. Paramètre : `language` |
| GET | `/api/v1/games/{id}/top-streamers` | Top des streams en direct. Paramètres : `limit` (5 ou 10), `language` (langues suivies) |

```http
GET /api/v1/games/32982/top-streamers?limit=5&language=fr
Authorization: Bearer hx_...
```

```json
{
  "data": [
    { "rank": 1, "login": "example", "display_name": "Example", "viewers": 12450,
      "title": "...", "language": "fr", "started_at": "2026-11-24T13:02:11Z" }
  ],
  "meta": { "game_id": "32982", "language": "fr", "collected_at": "2026-11-24T14:05:00Z" }
}
```

Chaque réponse renvoie `meta.collected_at`, la date du snapshot utilisé. Les erreurs ont toujours la même forme :

```json
{ "error": { "code": "validation_failed", "message": "...", "fields": { "limit": ["..."] } } }
```

| Code HTTP | `error.code` |
|---|---|
| 401 | `unauthenticated` |
| 404 | `not_found` |
| 422 | `validation_failed` |
| 429 | `rate_limited` (avec l'en-tête `Retry-After`) |

## Collecte

À chaque cycle, le collecteur :

1. récupère un token d'application Twitch (flux *client credentials*), gardé en cache jusqu'à son expiration ;
2. lit les `HELIXIR_GAMES_LIMIT` premiers jeux de `GET /games/top` ;
3. pour chaque jeu, parcourt `GET /streams?game_id=` page par page jusqu'à la disparition du curseur, dans la limite de `HELIXIR_MAX_PAGES` pages (au-delà, `is_estimate` vaut `true`) ;
4. additionne streams et spectateurs, au total et par langue, et garde le top 10 global et le top 10 de chaque langue de `HELIXIR_TRACKED_LANGUAGES` ;
5. enregistre tous les snapshots du cycle en une seule transaction.

Un jeu en échec n'empêche pas les autres d'être collectés. Les erreurs 429 et 5xx sont réessayées trois fois avec un délai exponentiel, et le collecteur ralentit quand l'en-tête `Ratelimit-Remaining` de Twitch devient bas.

## Configuration

| Variable | Défaut | Rôle |
|---|---|---|
| `TWITCH_CLIENT_ID`, `TWITCH_CLIENT_SECRET` | | Application Twitch |
| `HELIXIR_GAMES_LIMIT` | 100 | Nombre de jeux suivis |
| `HELIXIR_MAX_PAGES` | 10 | Pages de 100 streams lues au plus par jeu |
| `HELIXIR_TRACKED_LANGUAGES` | `fr,en` | Langues avec un top streamers dédié |
| `HELIXIR_RATE_LIMIT` | 60 | Requêtes par minute et par clé d'API |
| `HELIXIR_CACHE_TTL` | 60 | Durée de cache des réponses, en secondes |
| `SEED_DEMO_DATA` | `true` | Charge les données de démonstration au démarrage |

## Clés d'API

```bash
docker compose exec app php artisan helixir:api-key:create "nom du client"
docker compose exec app php artisan helixir:api-key:revoke <id>
```

Seule une empreinte SHA-256 de la clé est stockée en base.

## Tests

```bash
php artisan test      # Pest, base SQLite en mémoire, Twitch simulé avec Http::fake()
vendor/bin/pint       # style de code
```

## Choix techniques

- **Laravel 13, PHP 8.4** : scheduler, client HTTP, cache, limitation de débit et validation fournis par le framework.
- **Contrôleurs fins** : validation dans des Form Requests, lecture des statistiques dans `GameStatsQuery`, format JSON dans des API Resources.
- **Un cycle, une transaction** : l'API ne sert jamais un cycle à moitié écrit, et un échec de collecte ne supprime jamais les dernières données valides.
- **Cache indexé sur le cycle** : la date du snapshot fait partie de la clé de cache, donc un nouveau cycle invalide les anciennes réponses sans rien purger.

## Feuille de route

- Historique par jeu (24 h, 7 j, 30 j), jeux en tendance, score d'opportunité, comparaison de jeux
- Redis pour le cache et files d'attente (un job par jeu) supervisées avec Horizon
- Agrégats horaires et purge des snapshots bruts
- Documentation OpenAPI générée, CI GitHub Actions
