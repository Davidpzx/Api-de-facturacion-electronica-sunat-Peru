#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force || true
fi

CERT_DST="storage/app/public/certificado/certificado.pem"
if [ ! -f "$CERT_DST" ] && [ -f "ejemplos-postman/certificado_prueba/certificado.pem" ]; then
    mkdir -p storage/app/public/certificado
    cp ejemplos-postman/certificado_prueba/certificado.pem "$CERT_DST"
    chown -R www-data:www-data storage/app/public/certificado
fi

php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

exec "$@"
