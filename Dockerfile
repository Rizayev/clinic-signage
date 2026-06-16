# syntax=docker/dockerfile:1

# ──────────────────────────────────────────────────────────────
# Stage 1 — build frontend assets (Vue SPA + player bundle)
# VITE_REVERB_* are BAKED INTO THE JS HERE. Must be prod values.
# ──────────────────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_APP_NAME="Clinic Signage"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

# ──────────────────────────────────────────────────────────────
# Stage 2 — PHP runtime (php-fpm + nginx + ffmpeg + supervisor)
# Shared by web / reverb / queue services (role via CONTAINER_ROLE)
# ──────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache nginx supervisor ffmpeg bash

# PHP extensions: pdo_mysql (DB), pcntl (queue/reverb signals),
# opcache (perf), plus common Laravel deps.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql pcntl bcmath opcache zip mbstring exif intl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# App source + production PHP deps
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts \
    && composer dump-autoload --optimize --no-dev --no-scripts

# Built frontend from stage 1
COPY --from=assets /app/public/build ./public/build

# Container config
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini         /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh   /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/public storage/app/private storage/framework/{cache,sessions,views} storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    # nginx workers run as www-data — its temp dirs (where large upload bodies are
    # buffered) must be writable by www-data, else POST /api/media → 500.
    && mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy \
       /var/lib/nginx/tmp/fastcgi /var/lib/nginx/tmp/uwsgi /var/lib/nginx/tmp/scgi \
    && chown -R www-data:www-data /var/lib/nginx

EXPOSE 80
ENTRYPOINT ["entrypoint"]
