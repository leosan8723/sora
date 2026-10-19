#!/bin/sh
# Runtime entrypoint for the Sora production container (see Dockerfile).
#
#   1. Point Apache at Railway's $PORT (default 8080).
#   2. Cache configuration against the real runtime environment.
#   3. Apply pending migrations ONLY when the operator explicitly sets
#      RUN_MIGRATIONS=true for one deploy (never automatic).
#   4. Hand off to Apache in the foreground.
#
# Route caching is deliberately NOT used: the SPA catch-all routes are
# closures, which are not serializable for `route:cache`.
set -e

PORT="${PORT:-8080}"
sed -ri "s/^Listen[[:space:]]+80\$/Listen ${PORT}/" /etc/apache2/ports.conf

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache

exec apache2-foreground
