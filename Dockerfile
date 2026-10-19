# Production container for the complete Sora stack — Laravel API + React SPA
# + private admin shell, all served by Apache on the same origin.
#
# Build stages
#   1. frontend  node:22-alpine — runs the Vite build into
#                backend/public/build (the exact artifact contract the
#                shared-hosting/cPanel deployment was validated against).
#   2. final     php:8.2-apache — application sources live OUTSIDE the
#                document root; only backend/public is exposed by Apache.
#
#/Runtime behavior (docker/entrypoint.sh):
#   - Apache listens on Railway's $PORT (default 8080).
#   - `config:cache` runs at boot so configuration always reflects the real
#     runtime environment (APP_KEY, APP_URL, DB_*, ADMIN_PATH, MAIL_*).
#   - Migrations NEVER run automatically. Set RUN_MIGRATIONS=true once to
#     apply pending migrations (see RAILWAY_DEPLOY.md).
#   - Health probes: `/up` (framework) and `/api/v1/health` (application).

# ---------- Stage 1: build the SPA into backend/public/build ----------
FROM node:22-alpine AS frontend

WORKDIR /repo/frontend
COPY frontend/ /repo/frontend/

# The Vite outDir is ../backend/public/build (same layout as the cPanel
# deployment) and the SPA build uses base /build/ so the shell document
# references /build/assets/* — exactly what routes/web.php and
# AdminPanelController expect behind Apache.
RUN npm ci --no-audit --no-fund && npm run build -- --base=/build/

# ---------- Stage 2: production PHP/Apache image ----------
FROM php:8.2-apache

# Laravel requires pdo_mysql (Railway MySQL), mbstring and opcache.
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application sources. Nothing outside backend/public is web-accessible.
COPY backend/ /var/www/sora/
COPY --from=frontend /repo/backend/public/build/ /var/www/sora/public/build/

WORKDIR /var/www/sora

RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions \
        storage/framework/views bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress \
        --optimize-autoloader \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

# Apache configuration: docroot = Laravel public dir; HTTPS is terminated by
# Railway's reverse proxy and X-Forwarded-* headers are consumed by Laravel's
# trusted-proxies middleware (bootstrap/app.php).
COPY docker/apache/sora.conf /etc/apache2/sites-available/sora.conf
COPY docker/entrypoint.sh /usr/local/bin/sora-entrypoint

RUN chmod +x /usr/local/bin/sora-entrypoint \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2dissite 000-default \
    && a2ensite sora \
    && a2enmod rewrite headers

# Railway's proxy target port (overridden by the $PORT variable at runtime).
ENV PORT=8080

ENTRYPOINT ["/usr/local/bin/sora-entrypoint"]
