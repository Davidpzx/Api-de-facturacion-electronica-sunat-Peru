#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force || true
fi

php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

exec "$@"
