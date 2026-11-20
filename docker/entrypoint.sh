#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    echo "Missing .env. Copy .env.docker.example to .env before starting Docker." >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs \
    storage/uploads bootstrap/cache public/demo
chown -R www-data:www-data storage bootstrap/cache public/demo

# Preserve the existing key; generate it only for a new local environment.
if [ "$1" = "php-fpm" ]; then
    if ! grep -Eq '^APP_KEY=.+$' .env; then
        php artisan key:generate --force --no-interaction
    fi
fi

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

# Workers should write files with the same ownership as PHP-FPM workers.
if [ "$1" = "php" ] || [ "$1" = "composer" ]; then
    exec su -s /bin/sh www-data -c 'exec "$@"' -- sh "$@"
fi

exec docker-php-entrypoint "$@"
