#!/bin/bash
set -euo pipefail

cd /var/www/html

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
    export ASSET_URL="${ASSET_URL:-$RENDER_EXTERNAL_URL}"
    export L5_SWAGGER_CONST_HOST="${L5_SWAGGER_CONST_HOST:-$RENDER_EXTERNAL_URL}"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# One-shot full wipe when CREDITFAST_RESET_DATABASE=true (then set it back to false).
if [ "${CREDITFAST_RESET_DATABASE:-false}" = "true" ]; then
    php artisan migrate:fresh --force --seed --no-interaction
else
    php artisan migrate --force
    php artisan db:seed --force --no-interaction
    php artisan users:keep-admin-only --force
fi

php artisan l5-swagger:generate

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
