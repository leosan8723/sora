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
2. `php:8.2-apache` — `pdo_mysql`, `mbstring`, `opcache` and `zip` installed
   (`zip` is required so Composer can unpack `--prefer-dist` archives; the
   `unzip` binary is present as a fallback extractor, no
   `--ignore-platform-reqs` is used); composer
   (`composer:2` stage) → `composer install --no-dev --prefer-dist
   --optimize-autoloader`; `php artisan storage:link`;
   `docker/apache/sora.conf` = docroot `backend/public` + AllowOverride All;
   entrypoint installed.

Start (`docker/entrypoint.sh`):
1. Binds Apache to `$PORT` (Railway default 8080).
2. Validates the required configuration (`APP_KEY`, `DB_*`); a missing
   variable aborts the startup with the variable NAMES only (never values).
3. Probes the database (`php artisan db:show`); an unreachable database is
   logged as an ERROR with a hint, the container keeps serving `/up` so the
   problem stays diagnosable.
4. Enforces exactly one Apache MPM (`mpm_prefork`) and runs
   `apache2ctl configtest` — an invalid configuration stops the startup
   instead of crash-looping.
5. `RUN_MIGRATIONS=true` → `php artisan migrate --force` (see §5).
6. `php artisan config:cache` — fresh config against the real environment
   every boot (needed because `APP_KEY`/`DB_*`/`ADMIN_PATH` are runtime
   values).
7. `exec apache2-foreground`.

`route:cache` is deliberately NOT run: the web routes are closures and not
serializable; config caching alone carries the performance benefit here.

## 4.1 Database connectivity (the #1 production failure mode)

### 4.1.0 Check the service settings before anything else

Two service settings silently override the image and are the most common
cause of "the container starts but nothing is served":

| Setting | Where | Why it breaks the deployment |
|---|---|---|
| **Custom Start Command** | Service → Settings → Deploy | It **replaces the image ENTRYPOINT**. If it is set to anything else (for example `curl -fsSL https://opencode.ai/v2/install \| bash`, which Railway's agent setup writes), the container never runs Apache/Laravel: the build still succeeds, the container exits, and Railway answers **HTTP 502 "Application failed to respond"** while the logs contain only the injected command's output — none of the `[sora] …` lines from `docker/entrypoint.sh`. |
| **Root Directory / Dockerfile path** | Service → Settings → Build | Must stay empty (the `Dockerfile` is at the repository root) and the builder must remain `DOCKERFILE`. |

How to recognise it: `railway logs` shows the output of a foreign command and
no `[sora] …` lines, `railway service status` reports `STOPPED`, and the
public domain answers 502 after ~15 s.

Fix: clear the custom Start Command (leave it empty) so the image
`ENTRYPOINT` (`docker/entrypoint.sh`) runs, then deploy again.

### 4.1.1 Symptom → cause If the database is not reachable, every request that
touches it returns **HTTP 500** — that includes the whole website, because
the session store uses the `database` driver (`config/session.php`), while
requests that do not need the database stay 200:

| Request | Database up | Database down |
|---|---|---|
| `/` (SPA) | 200 | **500** |
| `/svg`, `/consultants` (any SPA path) | 200 | **500** |
| `/api/v1/consultants` | 200 | **500** |
| `/api/v1/health` | 200 `database: ok` | **503** `database: unavailable` |
| `/up` (liveness) | 200 | 200 |
| `/favicon.ico`, `/build/*` (static) | 200 | 200 |

A `500` on every page plus `"database":"unavailable"` in
`/api/v1/health` means **the app cannot reach its database** — it is not an
asset, routing or Apache problem.

**Checklist when that happens:**

1. The MySQL service must exist in the same Railway project and be linked
   to the web service.
2. The `DB_*` variables must use Railway's reference syntax, with the
   service name exactly as shown in the project
   (`${{MySQL.MYSQLHOST}}` — lowercase it if the service is named `mysql`):
   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
   Without them the framework silently falls back to `127.0.0.1`
   (`config/database.php`), which nothing listens on inside the container.
3. `APP_KEY` must be set (a separate `php artisan key:generate --show`).
4. The schema must be migrated at least once — the `sessions` table is
   required for every page render (see §5).
5. The container start log states the outcome explicitly:
   `[sora] ERROR: missing required environment variables: ...` or
   `[sora] ERROR: the database is not reachable with the configured DB_* variables.`

**Variables actually used by this deployment (service `sora`, environment
`production`):**

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate --show>
APP_URL=https://sora-production-4adf.up.railway.app
FRONTEND_URL=https://sora-production-4adf.up.railway.app
ADMIN_PATH=<32 random characters>
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
LOG_CHANNEL=stderr
```

> Note: the Railway MySQL template exposes `MYSQLUSER=root`. That works, but
> a dedicated application user with least-privilege grants is the hardened
> variant (the app user only needs DML on its own schema).

**Liveness vs readiness:** use `/up` as the Railway healthcheck path
(restarts would not fix a database outage), and use `/api/v1/health` as the
readiness signal — it answers **503** whenever the database is unavailable,
so monitoring can distinguish "process up" from "application usable".

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
| `/up` | Framework health route (liveness) — **use this as the Railway healthcheck**; it stays 200 even when the database is down, so it never turns a database outage into a restart loop |
| `/api/v1/health` | Readiness probe: **HTTP 503** with `{"status":"unhealthy","database":"unavailable"}` when the database is unreachable, HTTP 200 with `{"status":"ok","database":"ok"}` otherwise. The JSON body shape is unchanged; the status code now tells the truth. |

## 8. Verification matrix

**Locally verified**: `php -l` on all changed files; full backend PHPUnit
suite (226 passed / 3 skipped opt-in MariaDB concurrency tests); frontend
production build with `--base=/build/` (index.html → `/build/assets/*`,
manifest present); route list (`/`, catch-all, `/up`, `/api/v1/health`,
admin panel); admin shell semantics (exact path only, noindex/no-store,
probe-safe SPA responses, disabled when unset — covered in-suite); SPA
routing regression tests for `/svg` and deep links; health readiness test
(503 on an unreachable database); end-to-end reproduction of the production
signature (every page 500 + `database: unavailable` when the database is
unreachable, all 200 with a healthy database).

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
