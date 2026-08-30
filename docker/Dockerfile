# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# 1. PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Copy manifests first so the dependency layer caches independently of source.
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev


# ---------------------------------------------------------------------------
# 2. Frontend assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build


# ---------------------------------------------------------------------------
# 3. Runtime — FrankenPHP (Caddy + PHP in one process)
# ---------------------------------------------------------------------------
# One container instead of nginx + php-fpm. On a 2 GB VPS that difference is
# the whole point.
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
        posix \
        sockets

# postgresql-client gives the entrypoint pg_isready; bash for the entrypoint
# itself; git and unzip are what Composer reaches for when installing packages.
RUN apk add --no-cache bash postgresql-client tzdata git unzip

# Composer lives in the runtime image too. In dev the source tree is bind-mounted
# over /app, which hides the vendor/ baked into the image — so a fresh clone has
# to be able to install dependencies from inside the container.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-vantage.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# app | worker | scheduler — the entrypoint branches on this.
ENV CONTAINER_ROLE=app

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
