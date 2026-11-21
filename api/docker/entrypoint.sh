#!/bin/sh
# migrate -> import the catalogue on first start only -> serve.
# Exits non-zero (compose restarts the container) if every product source fails.
set -eu

# The source is bind-mounted in dev; keep vendor in sync with composer.lock (fast no-op when up to date).
composer install --no-interaction --no-progress --prefer-dist

# Config comes from real env vars (docker-compose). An empty .env stops Laravel/PHPUnit warning that it is missing.
[ -f .env ] || touch .env

# APP_KEY must be stable: it encrypts the session and remember-me cookies (admin + shop login).
# A set APP_KEY env var wins; otherwise generate one once and keep it in storage/app/.app-key
# (gitignored, survives restarts through the bind mount).
if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE=storage/app/.app-key
    if [ ! -s "$KEY_FILE" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
        echo "APP_KEY not set; generated one and stored it in api/$KEY_FILE."
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

php artisan migrate --force
php artisan catalogue:import --if-empty

# Admin panel (/admin): bootstrap the first admin from ADMIN_* (idempotent; never resets a password)
# and build the front-end when the build is missing or older than its sources.
if [ "${ADMIN_ENABLED:-false}" = "true" ]; then
    if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
        php artisan admin:create --from-env
    else
        echo "ADMIN_EMAIL/ADMIN_PASSWORD not set; create an admin with: docker compose exec api php artisan admin:create --email=you@example.com"
    fi

    # Keep node_modules in sync with package-lock.json (fast no-op when up to date).
    [ -d node_modules/.bin ] && [ node_modules/.package-lock.json -nt package-lock.json ] \
        || npm ci --no-audit --no-fund
    MANIFEST=public/build/manifest.json
    if [ ! -f "$MANIFEST" ] || [ -n "$(find resources/js resources/css routes/web.php package-lock.json vite.config.ts -newer "$MANIFEST" -not -path 'resources/js/actions/*' -not -path 'resources/js/routes/*' -not -path 'resources/js/wayfinder/*' 2>/dev/null | head -n 1)" ]; then
        echo "Building the admin panel (first start takes ~20s)..."
        npm run build
    fi
fi

# PHP's built-in server with Laravel's router script (what `artisan serve` runs underneath).
# Not `artisan serve` itself: it strips container env vars from its workers, so DB_* etc. would be lost.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
echo "API listening on http://0.0.0.0:8000"
# The router script resolves public/index.php from the working directory. PHP errors go to the log, never the response.
cd public
exec php -d display_errors=0 -d log_errors=1 -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
