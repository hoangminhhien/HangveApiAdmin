FROM composer:2.8 AS composer

# composer.json and composer.lock require PHP 7.4.
FROM php:7.4.33-fpm-bullseye AS app

ENV COMPOSER_ALLOW_SUPERUSER=1

# Bullseye packages have moved to Debian's archive. Keep signature verification;
# only disable the expiry check for the frozen repository metadata.
RUN printf '%s\n' \
        'deb [check-valid-until=no] https://archive.debian.org/debian bullseye main' \
        'deb [check-valid-until=no] https://archive.debian.org/debian-security bullseye-security main' \
        > /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libfcgi-bin libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
        libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd mbstring pcntl pdo_mysql soap zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

# Cache dependency downloads until the lock file changes.
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress \
        --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
        storage/uploads bootstrap/cache public/demo \
    && composer dump-autoload --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache public/demo

EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine AS nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

# Single-container HTTP service for Render. Compose keeps using app/nginx targets.
FROM app AS render
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default
COPY docker/render/nginx.conf.template /etc/nginx/render.conf.template
COPY docker/render/supervisord.conf /etc/supervisor/render.conf
COPY docker/render/entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod +x /usr/local/bin/render-entrypoint
EXPOSE 10000
ENTRYPOINT ["render-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/render.conf"]
