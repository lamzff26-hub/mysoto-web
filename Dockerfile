# syntax=docker/dockerfile:1

# ============================================================
# Stage 1 — Build asset front-end (Vite + Tailwind)
# ============================================================
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm ci
COPY . .
RUN npm run build

# ============================================================
# Stage 2 — Dependency PHP (Composer, tanpa dev)
# ============================================================
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ============================================================
# Stage 3 — Runtime (nginx + php-fpm)
# ============================================================
FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

# Ekstensi PHP: pdo_mysql (DB), zip+gd (ekspor Excel & gambar PDF),
# bcmath/intl (umum), opcache (performa). gettext untuk envsubst.
RUN apk add --no-cache \
        nginx gettext \
        libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev icu-dev oniguruma-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd bcmath intl opcache \
 && rm -rf /var/cache/apk/*

# Composer binary (untuk dump-autoload final)
COPY --from=vendor /usr/bin/composer /usr/bin/composer

# Source aplikasi + vendor + asset hasil build
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Selesaikan autoload + package discovery Laravel/Filament
RUN composer dump-autoload --optimize --no-interaction

# Konfigurasi PHP & izin direktori writable
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# nginx + entrypoint
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
