#!/bin/sh
# Runtime entrypoint for the Sora production container (see Dockerfile).
#
#   1. Point Apache at Railway's $PORT (default 8080).
#   2. Guarantee that exactly one Apache MPM is loaded.
#   3. Cache configuration against the real runtime environment.
#   4. Apply pending migrations ONLY when the operator explicitly sets
#      RUN_MIGRATIONS=true for one deploy (never automatic).
#   5. Hand off to Apache in the foreground.
#
# Route caching is deliberately NOT used: the SPA catch-all routes are
# closures, which are not serializable for `route:cache`.
set -e

PORT="${PORT:-8080}"
sed -ri "s/^Listen[[:space:]]+80\$/Listen ${PORT}/" /etc/apache2/ports.conf
echo "[sora] Apache listen configuration: $(grep -E '^Listen' /etc/apache2/ports.conf | tr '\n' ' ')"

if [ -z "${APP_KEY:-}" ]; then
    echo "[sora] WARNING: APP_KEY is not set in the environment. Encrypted values (session/cookie encryption, signed URLs) will fail at runtime." >&2
fi

# --- Apache MPM: exactly one may be loaded ---------------------------------
# Apache aborts with "AH00534: apache2: Configuration error: More than one
# MPM loaded" when two MPMs are active. The image build already enforces
# mpm_prefork (see Dockerfile), but the second MPM can be (re)enabled
# between image build and container start, so the state is enforced again
# here, on the running container, before Apache is started.
#
# mpm_prefork is required: the image uses mod_php, which does not work with
# mpm_event/mpm_worker.
#
# a2dismod is idempotent ("already disabled" is not a failure); the fallback
# only protects against a non-zero exit for an already-disabled module.
a2dismod mpm_event mpm_worker || true

# Enabling the intended MPM must succeed: a failure here means Apache would
# not start with mod_php, so stop immediately with a clear message instead of
# a crash loop.
if ! a2enmod mpm_prefork; then
    echo "[sora] ERROR: could not enable mpm_prefork (required by mod_php). Aborting startup." >&2
    exit 1
fi

# Diagnostics: which MPM modules are actually loaded right now.
echo "[sora] Apache MPM modules: $(apache2ctl -M 2>/dev/null | grep -i 'mpm_' | tr -s ' ' | tr '\n' ' ')"

# A broken configuration must fail the startup here, not crash-loop later.
if ! apache2ctl configtest; then
    echo "[sora] ERROR: Apache configuration is invalid (see configtest output above). Aborting startup." >&2
    exit 1
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache

exec apache2-foreground
