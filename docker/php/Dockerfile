# Image de dev : PHP 8.4 en CLI + extension PostgreSQL + Composer.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["entrypoint"]
# Serveur PHP intégré lancé directement : "artisan serve" filtre les variables
# d'environnement et retomberait sur la base SQLite du fichier .env.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
