FROM composer:2.8 AS dependencies

WORKDIR /app

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

FROM php:8.3-cli-alpine AS runtime

RUN apk add --no-cache libpq oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pcntl pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html

COPY apps/api ./
COPY --from=dependencies /app/vendor ./vendor
COPY deploy/api-entrypoint.sh /usr/local/bin/fieldops-entrypoint

RUN chmod +x /usr/local/bin/fieldops-entrypoint \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000

ENTRYPOINT ["fieldops-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
