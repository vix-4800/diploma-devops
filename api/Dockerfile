FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* /app/
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

COPY . /app
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.3-cli-alpine

WORKDIR /app

RUN set -eux; \
    apk add --no-cache libpq; \
    apk add --no-cache --virtual .build-deps postgresql-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql; \
    apk del .build-deps

RUN mkdir -p /app/logs

COPY --from=vendor /app /app

ENV APP_HOST=0.0.0.0 \
    APP_PORT=8080

EXPOSE 8080

CMD ["sh", "-lc", "php -S ${APP_HOST}:${APP_PORT} -t public public/index.php"]
