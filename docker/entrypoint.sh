#!/bin/sh
set -eu
case "${PORT:-8080}" in *[!0-9]*|'') echo 'PORT deve ser numérica' >&2; exit 1 ;; esac
sed -i "s/^Listen 80$/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/:8080>/:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/storage/app/private
chown -R www-data:www-data /app/storage /app/bootstrap/cache
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi
exec "$@"
