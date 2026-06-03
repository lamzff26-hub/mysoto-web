#!/bin/sh
set -e

# Port dari Railway (fallback 8080 untuk lokal).
: "${PORT:=8080}"
export PORT

# Render konfigurasi nginx dengan PORT runtime (hanya ${PORT} yang disubstitusi).
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

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
exec nginx -g 'daemon off;'
