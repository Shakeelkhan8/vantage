#!/usr/bin/env bash
set -euo pipefail

ROLE="${CONTAINER_ROLE:-app}"

cd /app

log() { printf '[entrypoint] %s\n' "$*" >&2; }

wait_for_tcp() {
    local host="$1" port="$2" label="$3" tries=0

    until nc -z "$host" "$port" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 90 ]; then
            log "gave up waiting for ${label} at ${host}:${port}"
            exit 1
        fi
        sleep 1
    done

    log "${label} is up at ${host}:${port}"
}

# The dev stack mounts a named volume at storage/framework, which starts empty.
ensure_storage_dirs() {
    mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
}

# In dev the source tree is bind-mounted over /app, which hides the vendor/
# baked into the image. On a fresh clone the host has no vendor/ at all, so
# without this the container boots into "failed to open vendor/autoload.php".
ensure_vendor() {
    if [ -f vendor/autoload.php ]; then
        return
    fi

    if [ "${APP_ENV:-local}" = "production" ]; then
        log "vendor/ is missing from a production image — refusing to install at runtime"
        exit 1
    fi

    if [ "$ROLE" = "app" ]; then
        log "vendor/ missing (fresh clone?) — running composer install, this takes a minute"
        composer install --no-interaction --no-progress
        return
    fi

    # Only the app container installs. Everyone else waits for it.
    log "waiting for the app container to install vendor/"
    local tries=0
    until [ -f vendor/autoload.php ]; do
        tries=$((tries + 1))
        if [ "$tries" -ge 300 ]; then
            log "vendor/ never appeared"
            exit 1
        fi
        sleep 2
    done
    log "vendor/ is ready"
}

ensure_storage_dirs
ensure_vendor

wait_for_tcp "${DB_HOST:-postgres}" "${DB_PORT:-5432}" "postgres"
wait_for_tcp "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "redis"

# Migrations run from exactly one container. Running them from the worker and
# the scheduler as well is how you get three concurrent migration attempts and
# a half-migrated database.
if [ "$ROLE" = "app" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    log "running migrations"
    php artisan migrate --force
fi

if [ "$ROLE" = "app" ]; then
    php artisan storage:link 2>/dev/null || true
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    # Stale caches in dev are a wasted afternoon.
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

case "$ROLE" in
    app)
        log "starting web server"
        exec "$@"
        ;;
    worker)
        log "starting horizon"
        exec php artisan horizon
        ;;
    scheduler)
        log "starting scheduler"
        exec php artisan schedule:work
        ;;
    *)
        log "unknown CONTAINER_ROLE '${ROLE}' (expected app|worker|scheduler)"
        exit 1
        ;;
esac
