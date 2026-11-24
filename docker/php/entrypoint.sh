#!/bin/sh
# Prépare l'application au démarrage du conteneur, puis lance la commande (CMD).
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Applique les migrations et charge les données de démo (idempotent).
php artisan migrate --force
php artisan db:seed --force

exec "$@"
