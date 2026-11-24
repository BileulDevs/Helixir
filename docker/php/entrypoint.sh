#!/bin/sh
# Prépare l'application au démarrage du conteneur, puis lance la commande (CMD).
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Un seul conteneur (app) applique les migrations, pour éviter deux migrations concurrentes.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force

    # Données de démo pour tester sans clé Twitch (idempotent).
    if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
        php artisan db:seed --force
    fi
fi

exec "$@"
