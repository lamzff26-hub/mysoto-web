#!/bin/sh
set -e

# Port dari Railway (fallback 8080 untuk lokal).
: "${PORT:=8080}"
export PORT

# Render konfigurasi nginx dengan PORT runtime (hanya ${PORT} yang disubstitusi).
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Generate .env dari environment variables jika belum ada
if [ ! -f /var/www/html/.env ]; then
    cat > /var/www/html/.env << EOF
APP_NAME=${APP_NAME:-Laravel}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_LEVEL=${LOG_LEVEL:-error}
DB_CONNECTION=${DB_CONNECTION:-sqlite}
DB_HOST=${DB_HOST:-}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-database.sqlite}
DB_USERNAME=${DB_USERNAME:-}
DB_PASSWORD=${DB_PASSWORD:-}
SESSION_DRIVER=${SESSION_DRIVER:-database}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-public}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}
CACHE_STORE=${CACHE_STORE:-database}
BROADCAST_CONNECTION=${BROADCAST_CONNECTION:-log}
EOF
fi

# Setup Laravel saat boot.
php artisan storage:link || true       # symlink public/storage -> storage/app/public
php artisan migrate --force || true    # jalankan migrasi yang tertunda (idempotent)

# Isi data awal HANYA bila RUN_SEED=true (set sekali saat deploy pertama,
# lalu matikan agar data demo tidak ter-reset tiap redeploy).
if [ "${RUN_SEED}" = "true" ]; then
    php artisan db:seed --force || true
fi

php artisan config:cache || true       # cache config (route:cache TIDAK dipakai: ada closure)
php artisan view:cache || true

# php-fpm di background, nginx di foreground (proses utama container).
php-fpm -D

# Verify nginx config is valid before starting
nginx -t || (echo "Nginx config error!" && exit 1)

# Start nginx in foreground (PID 1 replacement)
exec nginx -g 'daemon off;'
