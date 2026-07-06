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
fi

# El volumen persistente se monta como root; php-fpm corre como www-data.
# Sin este chown en runtime, Greenter no puede escribir XML/CDR en el volumen.
mkdir -p storage/app/public storage/app/sunat storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

exec "$@"
