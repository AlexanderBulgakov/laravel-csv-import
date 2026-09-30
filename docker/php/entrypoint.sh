#!/bin/sh
set -e

cd /var/www/html

if [ ! -f artisan ]; then
    echo "No Laravel app found in ./src — bootstrapping a new project..."
    composer create-project laravel/laravel:^8.0 . --prefer-dist --no-interaction
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

exec "$@"
