# Laravel API for local development (php artisan serve). Production would use php-fpm/nginx or Octane.
FROM php:8.4.28-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11.2 /usr/bin/composer /usr/bin/composer

# Node for the admin panel build (/admin, Inertia + React starter kit). It lives in this image, not a
# separate service, because the Wayfinder Vite plugin runs `php artisan wayfinder:generate` during
# the build, so PHP and Node must be in the same container.
COPY --from=node:22.22.0-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22.22.0-bookworm-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /var/www/html

# Install dependencies in their own layer so code changes don't re-download them.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

# Admin front-end dependencies (lockfile only; .npmrc disables install scripts).
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize --no-scripts \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
