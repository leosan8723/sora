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

# --- Required configuration ------------------------------------------------
# Only the NAMES of missing variables are printed, never their values.
# Without DB_* the framework silently falls back to 127.0.0.1 (see
# config/database.php) and every request that touches the database fails
# with HTTP 500, so a missing configuration must fail fast and loudly.
missing=""
for var in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
        missing="$missing $var"
    fi
done

if [ -n "$missing" ]; then
    echo "[sora] ERROR: missing required environment variables:$missing" >&2
    echo "[sora] HINT: link the Railway MySQL service to this service and set DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD (references like \${{MySQL.MYSQLHOST}}), and set APP_KEY (php artisan key:generate --show)." >&2
    exit 1
fi

# --- Database connectivity probe -------------------------------------------
# Fails the deployment early with an actionable message instead of a silent
# stream of HTTP 500 responses. A plain PDO connect is used on purpose:
# artisan db:* commands query performance_schema/information_schema, which a
# least-privilege database user may not read, and would report a false
# alarm. No credentials are printed (PDO messages never contain a password).
if ! php -r '
$driver = getenv("DB_CONNECTION") ?: "mysql";
$host = (string) getenv("DB_HOST");
$port = (string) (getenv("DB_PORT") ?: 3306);
$database = (string) getenv("DB_DATABASE");
$dsn = $driver === "pgsql"
    ? "pgsql:host=$host;port=$port;dbname=$database"
    : "$driver:host=$host;port=$port;dbname=$database";
try {
    new PDO($dsn, (string) getenv("DB_USERNAME"), (string) getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 5]);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
' 2>/dev/null; then
    echo "[sora] ERROR: the database is not reachable with the configured DB_* variables." >&2
    echo "[sora] HINT: check that the MySQL service is linked to this service, that DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD match it, and that the schema has been migrated (php artisan migrate --force)." >&2
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
