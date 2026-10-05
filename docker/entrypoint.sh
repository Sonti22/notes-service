#!/bin/sh
set -eu
mkdir -p /data
if [ ! -f /data/app-key ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app-key
fi
export APP_KEY="$(cat /data/app-key)"
touch /data/database.sqlite
chown -R www-data:www-data /data storage bootstrap/cache
php artisan migrate --force
php artisan config:cache
exec "$@"
