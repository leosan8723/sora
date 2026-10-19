# Railway Deployment — Sora (single service)

Deployment target: one Railway **web service** serving everything — Laravel
API + JSON API, the public React/TypeScript SPA, and the private admin
shell — on a single domain, backed by one Railway **MySQL** database.

Build is fully derived from the repository (root `Dockerfile`); there is no
startup script that just satisfies a detector, and the shared-hosting
(cPanel) deployment documented in `deploy/sora-htdocs-*/DEPLOY_README.md` is
unaffected.

---

## 1. What runs where

| Layer | Implementation |
|---|---|
| Web server | Apache (php:8.2-apache image) — docroot `backend/public` |
| App | Laravel 12 (`backend/`), sources **outside** the docroot |
| SPA | Vite build emitted into `backend/public/build` at image build time (`--base=/build/`) |
| Admin shell | `/{ADMIN_PATH}` — served by `AdminPanelController` (server-side `hash_equals`) |
| Database | Railway MySQL 8.x (`mysql` template) — schema created via migrations |
| Sessions/cache/queue | Laravel `database` driver (no Redis needed) |

Routing in production (container): Apache serves `/build/*` static assets
directly; Apache + Laravel's `public/.htaccess` forwards everything else to
`index.php`; Laravel serves `/` and all deep links as the SPA shell; the
JSON API lives under `/api/v1/*`; `/{ADMIN_PATH}` renders the private admin
shell (noindex + no-store).

## 2. Required services (creation order matters)

1. **MySQL** — Project canvas → `+ New → Database → MySQL`. Leave public
   networking disabled (the app connects over Railway's private network).
   Provision this FIRST so migrations have a reachable database on the
   first controlled run.
2. **Web service** — `+ New → GitHub Repo` → select this repository.
   Settings:
   - **Build**: leave command empty — Railway builds the root `Dockerfile`.
   - **Start command**: leave empty (the image `ENTRYPOINT` handles port,
     config cache, and the optional migration trigger).
   - **Healthcheck path**: `/up`
   - **Replicas**: 1 (`scale` later only after verifying DB-locked
     migration behavior on restarts).

## 3. Service variables (Railway dashboard, web service)

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate --show, run locally; never commit>
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
APP_LOCALE=fa
FRONTEND_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}   # same origin — used for email links
ADMIN_PATH=<long random value, e.g. 24+ chars>    # empty string disables the shell entirely
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr     # console-observable in Railway; file logs are ephemeral
MAIL_MAILER=smtp
MAIL_HOST=<smtp host>
MAIL_PORT=<587/465>
MAIL_USERNAME=<smtp user>
MAIL_PASSWORD=<smtp password>
MAIL_FROM_ADDRESS="no-reply@<your-domain>"
MAIL_FROM_NAME="${APP_NAME}"
```

- `FRONTEND_URL` must be the public URL of the SPA **exactly**; the email
  verification and password-reset messages point users at it.
- `VITE_API_BASE_URL` is NOT needed: the SPA calls same-origin `/api/v1`.
- Do not create a `PORT` variable; Railway provides it and the entrypoint
  binds Apache to it (default 8080).
- Never commit `.env` or secret values anywhere in the repository.

## 4. Build & start (what the image actually does)

Build stages:
1. `node:22-alpine` — `npm ci && npm run build -- --base=/build/` →
   `backend/public/build` (same artifact contract as the cPanel package:
   shell document references `/build/assets/*`, manifest at
   `build/.vite/manifest.json`).
2. `php:8.2-apache` — `pdo_mysql`, `mbstring`, `opcache` installed; composer
   (`composer:2` stage) → `composer install --no-dev --prefer-dist
   --optimize-autoloader`; `php artisan storage:link`;
   `docker/apache/sora.conf` = docroot `backend/public` + AllowOverride All;
   entrypoint installed.

Start (`docker/entrypoint.sh`):
1. Binds Apache to `$PORT` (Railway default 8080).
2. `RUN_MIGRATIONS=true` → `php artisan migrate --force` (see §5).
3. `php artisan config:cache` — fresh config against the real environment
   every boot (needed because `APP_KEY`/`DB_*`/`ADMIN_PATH` are runtime
   values).
4. `exec apache2-foreground`.

`route:cache` is deliberately NOT run: the web routes are closures and not
serializable; config caching alone carries the performance benefit here.

## 5. Database migrations — controlled procedure (never automatic)

Schema is created empty; nothing from any existing database is migrated
until you explicitly export/import it yourself.

- **First deploy / schema reset**: set service variable
  `RUN_MIGRATIONS=true`, deploy once, confirm logs show all migrations
  applied, then REMOVE the variable.
- **Schema change later**: push code containing new migrations, then redeploy
  with `RUN_MIGRATIONS=true` once. Remove the variable afterwards.
- **Why not automatic**: container starts that happen without an operator
  (crash-loop while the DB is temporarily unreachable, multi-replica
  restarts) would either fail deployment or race on the `migrations` table.
- **Existing MariaDB data**: NOT migrated automatically. If the cPanel
  database contains real data you want to keep, export it with
  `mysqldump` (or phpMyAdmin) and import into the Railway MySQL service
  **before** the first migration run (then `RUN_MIGRATIONS=false`).
  MariaDB → MySQL 8 import must be reviewed manually (charset/schema quirks
  are reviewed in this repo: all 22 migrations are MySQL-8-compatible).

## 6. Initial administrator (secure procedure)

```
railway link                      # link the CLI to the project
railway ssh --service <web-service>
php artisan admin:create --name="Your Name" --email="you@domain" --password="<strong>"
exit
```

- The command refuses to run if any administrator already exists.
- Credentials are never stored in code, docs, or variables; `ADMIN_PATH`
  only protects the shell route — all admin API endpoints additionally
  require authentication + the admin role.

## 7. Health checks

| Path | Meaning |
|---|---|
| `/up` | Framework health route — **use this as the Railway healthcheck** |
| `/api/v1/health` | Application health; NOTE: returns HTTP 200 even when the database is unreachable (`database: unavailable`) — do not use it as a database-sensitive platform healthcheck |

## 8. Verification matrix

**Locally verified**: `php -l` on all changed files; full backend PHPUnit
suite (219 passed / 3 skipped opt-in MariaDB concurrency tests); frontend
production build with `--base=/build/` (index.html → `/build/assets/*`,
manifest present); route list (`/`, catch-all, `/up`, `/api/v1/health`,
admin panel); admin shell semantics (exact path only, noindex/no-store,
probe-safe SPA responses, disabled when unset — covered in-suite).

**Requires a live Railway environment (not yet executed)**: Docker image
build success (no Docker on this workstation), real cluster HTTPS/proxy
behavior, MySQL 8 runtime behavior of the migrations, `RUN_MIGRATIONS`
run, `railway ssh` admin creation, SMTP delivery (real mail), public
domain DNS.

## 9. Limitations & notes

- Ephemeral filesystem: `storage/` contents are lost on redeploy. Fine for
  current code (no file uploads exist); do not add file persistence without
  adding a Railway volume or object storage.
- One-time cost: first build downloads PHP composer packages; builds are
  layer-cached on Railway after that.
- `MAIL_MAILER=log` (default in `.env.example`) only writes to disk and is
  silently lost — real deployments must use SMTP.
- The cPanel deployment keeps its own package under `deploy/`; it is not
  affected by this service. The packaging patch for bootstrap
  (`usePublicPath(htdocs)`) remains deploy-package-specific.
