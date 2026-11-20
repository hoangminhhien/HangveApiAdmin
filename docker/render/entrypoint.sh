#!/bin/sh
set -eu
cd /var/www/html

PORT=${PORT:-10000}
case "$PORT" in
    ''|*[!0-9]*) echo 'PORT must be a number.' >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo 'PORT must be between 1 and 65535.' >&2
    exit 1
fi

# Render injects environment variables directly; a .env file is not required.
if [ -z "${APP_KEY:-}" ]; then
    echo 'Set APP_KEY in Render Environment to the existing Laravel application key.' >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs \
    storage/uploads bootstrap/cache public/demo /run/php
chown -R www-data:www-data storage bootstrap/cache public/demo

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    ln -s /var/www/html/storage/app/public public/storage
fi

sed "s/__PORT__/$PORT/g" /etc/nginx/render.conf.template > /etc/nginx/conf.d/render.conf
nginx -t
exec "$@"
