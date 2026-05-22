FROM php:8.3-cli

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git curl unzip libpq-dev libzip-dev libpng-dev libonig-dev \
        libxml2-dev libcurl4-openssl-dev libicu-dev ca-certificates \
 && docker-php-ext-configure intl \
 && docker-php-ext-install pdo pdo_pgsql pgsql zip gd bcmath intl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chmod -R 0777 storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "\
  set -e; \
  [ -d vendor ] && [ -f vendor/autoload.php ] || composer install --prefer-dist --no-interaction; \
  [ -f .env ] || cp .env.example .env; \
  grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force; \
  php artisan storage:link --force >/dev/null 2>&1 || true; \
  php artisan filament:assets >/dev/null 2>&1 || true; \
  php artisan migrate --force; \
  php artisan db:seed --force || true; \
  php artisan serve --host=0.0.0.0 --port=8000 \
"]
