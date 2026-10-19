# Architecture & Technical Decisions

Consultation Booking and Session Management Platform

Status: approved for Phase 1 (2026-10-16); Phase 2, 3, 4, 5, 6, and 7 additions recorded (2026-10-16)
Phase: 7 — Client and Consultant Dashboards

---

## 1. Summary

A Persian (Farsi), RTL-first consultation booking platform with:

- **Backend**: Laravel 12 (REST API) on PHP 8.2.12 (XAMPP).
- **Frontend**: React + TypeScript (Vite) single-page application.
- **Database**: MariaDB 10.4.32 (XAMPP InnoDB) — deviates from the original
  PostgreSQL mandate; see [Section 4](#4-database-decision).

The customer-facing application is Persian and fully RTL. Code, identifiers,
and technical documentation are English.

---

## 2. Environment Baseline (verified 2026-10-16)

| Component | Version / Status |
| --- | --- |
| PHP (CLI + Apache mod_php) | 8.2.12 ZTS (XAMPP), `C:\xampp\php\php.exe` |
| PHP extensions | ctype, curl, dom, fileinfo, json, libxml, mbstring, openssl, pdo, **pdo_mysql**, tokenizer, xml, bcmath, mysqli, **intl (enabled Phase 1)**, **gd (enabled Phase 1)**, **zip (enabled Phase 1, required by Composer)** |
| Composer | 2.10.3 (portable, installed Phase 1 at `C:\tools\composer`) |
| Node.js / npm | 24.16.0 / 11.13.0 |
| Git | 2.55.0 (workspace initialized as a repository in Phase 2) |
| MariaDB | 10.4.32 (XAMPP), running, port 3306 |
| Apache | XAMPP, running, ports 80/443, loads PHP via `php8apache2_4.dll` (mod_php) |
| PostgreSQL | Not installed |
| Docker | Not available |

Existing local assets that **must not be disturbed**: the `akhavan` database and
the `akhavan.local` Apache vhost (`C:/Users/GuGuli/Desktop/router/public`).
phpMyAdmin and the `test` database also exist.

---

## 3. Technology Stack (final)

| Layer | Choice | Notes |
| --- | --- | --- |
| Backend framework | Laravel 12.x | Laravel 13 requires PHP ≥ 8.3; current PHP 8.2.12 caps us at 12.x |
| Backend language | PHP 8.2.12 | Provided by XAMPP |
| API style | REST, JSON | Versioned under `/api/v1` |
| Database | MariaDB 10.4.32 | XAMPP InnoDB; see Section 4 |
| Auth | Laravel Sanctum (Bearer tokens) | SPA + token API |
| Authorization | Role column + Laravel Policies/Gates | No third-party permission package |
| Frontend build | Vite 8.3 | Resolved at scaffold time (`create-vite` 9.2.1) |
| Frontend framework | React 19.2 + TypeScript 6.0 | |
| Routing | React Router 8 (package `react-router`) | |
| Server state | TanStack Query 5.104 | |
| Forms | react-hook-form 7.90 + Zod 4.6 (`@hookform/resolvers`) | |
| Styling | Tailwind CSS 4.3 + design tokens (`@theme`) | Logical properties for RTL |
| Motion | Motion 14 (`motion/react`) | Reimplements `sample.html` reveal/hover behaviour |
| Font | `@fontsource-variable/vazirmatn` (self-hosted) | No external font CDN |
| Backend code style | Laravel Pint | `vendor/bin/pint --test` is a gate |
| Backend tests | PHPUnit (Laravel 12 default) | Pest is not installed |
| Frontend tests | Vitest + Testing Library | Planned; not installed in Phase 2 |
| Frontend linter | oxlint (Vite 8 template default) | Replaces ESLint in this scaffold |
| Dates (Jalali) | Persian calendar conversion + `Intl` | |

Exact dependency versions are pinned in `composer.lock` / `package-lock.json`
during Phase 2 and must not be guessed elsewhere.

---

## 4. Database Decision

### 4.1 Evaluation

| Criterion | PostgreSQL | MySQL 8.x | **MariaDB 10.4.32 (chosen)** |
| --- | --- | --- | --- |
| Installed / available now | No (no service, no port 5432) | No | Yes — running, login verified |
| PHP driver | `pdo_pgsql` disabled → `php.ini` edit **+ Apache restart** | New install, `pdo_mysql` already on | `pdo_mysql` already enabled |
| Laravel 12 support | PostgreSQL 10.0+ | MySQL 5.7+ | MariaDB 10.3+ (documented) |
| Setup cost | Installer, Windows service, config | New server install | Zero |
| Restart required | Yes (affects `akhavan.local`) | No (CLI) / service install | No |
| Booking integrity | Strongest (EXCLUDE/GiST, `SKIP LOCKED`) | InnoDB `FOR UPDATE` + unique index | InnoDB `FOR UPDATE` + unique index |
| Operational risk to existing work | Moderate | Moderate | None |

### 4.2 Decision

**MariaDB 10.4.32**, provided by the running XAMPP instance, is the primary
database.

### 4.3 Reasoning

1. Already running and verified; zero new services and zero restarts.
2. `pdo_mysql` is already enabled, so no database-related `php.ini` change is
   needed (and therefore no Apache restart).
3. Laravel supports MariaDB 10.3+ first-class; InnoDB provides the transactions,
   row locking, and unique constraints required for conflict-free booking.
4. PostgreSQL would add an installer, a Windows service, a `php.ini` change, and
   an Apache restart — cost with no practical gain at this scale.
5. MySQL would duplicate a server already present and maintained.

### 4.4 Consequences and mitigations

- This **deviates from the original PostgreSQL mandate** in the earlier drafts of
  `AGENTS.md` / `DESIGN.md`; those documents were updated to match this decision
  with explicit user approval.
- MariaDB 10.4 lacks `SELECT ... FOR UPDATE SKIP LOCKED` (added in 10.6). The
  booking design therefore relies on a unique slot key plus a transaction with a
  plain `FOR UPDATE` / duplicate-key handling (Section 9).
- SQL portability is preserved by building all schema through Laravel's schema
  builder and avoiding vendor-specific constructs, so a later migration to
  PostgreSQL is possible if needed.
- Revisit trigger: if requirements demand `EXCLUDE` range constraints or
  `SKIP LOCKED`, or if PHP is upgraded to ≥ 8.3, re-evaluate PostgreSQL.

---

## 5. Repository Layout

```
/ (workspace root)
├─ AGENTS.md                     # agent working rules
├─ DESIGN.md                     # product design specification
├─ IMPLEMENTATION_PLAN.md        # phased plan + progress
├─ ARCHITECTURE.md               # this document
├─ sample.html                   # visual reference (do not modify)
├─ backend/                      # Laravel 12 application (Phase 2)
└─ frontend/                     # Vite + React + TS SPA (Phase 2)
```

`sample.html` is reference-only and is never modified or shipped.

---

## 6. Runtime & Development Workflow

- Backend dev server: `php artisan serve` on `127.0.0.1:8000`. This uses the PHP
  CLI and **does not involve Apache**, so the XAMPP web server (and the
  `akhavan.local` site) is untouched during development.
- Frontend dev server: `npm run dev` on `127.0.0.1:5173`, calling the API at
  `http://127.0.0.1:8000/api/v1`.
- Apache/phpMyAdmin remain available for database inspection only.
- Production serving strategy is documented in Phase 12; it is out of scope now.

### 6.1 Development setup (Phase 2)

PHP is not on `PATH`; invoke it as `C:\xampp\php\php.exe`. Composer is available
as `composer` (wrapper at `C:\tools\composer`).

Backend (`backend/`):

```
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan test
C:\xampp\php\php.exe vendor\bin\pint --test
```

Frontend (`frontend/`):

```
npm install
npm run dev      # http://127.0.0.1:5173
npm run build    # tsc -b && vite build
npm run lint     # oxlint
```

`backend/.env` must be copied from `backend/.env.example` and filled with the
local database credentials. `frontend/.env` is optional; `VITE_API_BASE_URL`
defaults to `http://127.0.0.1:8000/api/v1` in `src/lib/api.ts`.

---

## 7. Backend Architecture (Laravel)

### 7.1 API conventions

- Base path: `/api/v1`.
- JSON request/response only.
- Success envelope: `{ "data": ... }` with optional `"meta"` for pagination.
- Error envelope: `{ "message": string, "errors": [{ "code", "field", "message" }] }`.
- Status codes used meaningfully: 200, 201, 204, 401, 403, 404, 409 (booking
  conflict), 422 (validation), 429 (rate limit).
- Pagination: page-based (`per_page`, `page`) via Laravel paginator.
- Output shaping: API Resources (never expose raw models or private fields).
- Validation: FormRequest classes per endpoint; shared business rules extracted
  into dedicated rule objects/services.

### 7.2 Authentication

Implemented in Phase 4.

- Laravel Sanctum personal access tokens (Bearer).
- Endpoints under `/api/v1/auth`: `POST /register`, `POST /login`,
  `POST /logout`, `GET /me`, `PUT /profile`, `PUT /password`.
- `login`/`register` return `{ data: { token, token_type: "Bearer", user } }`;
  `logout` and `password` return 204; `me`/`profile` return a `UserResource`.
- Registration always creates a `client`; privileged attributes (`role`,
  `is_active`) are never accepted from the payload (FormRequest whitelists the
  validated fields).
- Passwords hashed with bcrypt via the `password => 'hashed'` cast; policy is
  `Password::min(8)->letters()->numbers()` (`AppServiceProvider`).
- Failed logins return a single generic message (422) for both unknown accounts
  and wrong passwords — no user enumeration. A deactivated account is rejected
  with 403 **only after** the password has been verified, so the suspended state
  cannot be discovered without valid credentials.
- Changing a password requires the current password and revokes every other
  token, keeping the active session.
- Rate limits (`AppServiceProvider`): login 5/min per email+IP and 20/min per IP;
  registration 10/min per IP; password change 5/min per user+IP. Limits use the
  configured cache store (array in tests).
- Unauthenticated API requests always receive `401` JSON. `bootstrap/app.php`
  overrides guest redirection so requests that omit `Accept: application/json`
  are not sent to a non-existent session `login` route.
- CSRF is not applicable to the token API; if a cookie/session mode is ever
  introduced it must re-enable CSRF protection.

### 7.3 Roles and authorization

Implemented in Phase 4 (primitives); resource policies are added per phase.

- `users.role` in {`client`, `consultant`, `admin`} (`App\Enums\UserRole`).
- Gates: `access-admin` (admin only), `access-consultant` (consultant or admin).
- `role:<roles...>` middleware (`App\Http\Middleware\EnsureUserHasRole`) guards
  routes by role and returns 403 for authenticated users without the role.
- Every protected action is authorized server-side via Policies/Gates; the
  frontend never decides permissions, it only reflects them.
- Client data isolation is enforced at the query level (scoping by authenticated
  user), not only in the UI.

### 7.4 Cross-cutting

- Mass assignment restricted with explicit `$fillable`.
- No sensitive data (passwords, tokens, consultation notes) in logs or responses.
- Deliberate eager loading / pagination to avoid N+1 and unbounded queries.

### 7.5 CORS

- `config/cors.php` allows only `FRONTEND_URL` over `api/*` with
  `supports_credentials = false`.
- Note: with a **single** exact origin, `fruitcake/php-cors` applies its
  "single origin" optimization and sets `Access-Control-Allow-Origin` to that
  configured origin on every response (it never reflects an arbitrary origin and
  never uses `*`). Disallowed origins still cannot read responses, because the
  browser requires the header to equal the request's own origin; the value is a
  fixed trusted origin, so this is not exploitable.

### 7.6 Discovery and consultant self-management

Implemented in Phase 5.

Public discovery (no authentication):

- `GET /api/v1/specialties` — the curated specialty catalog.
- `GET /api/v1/consultants` — discoverable consultants (`active` and
  `verified`). Query filters: `q` (name/headline keyword), `specialty` (slug,
  422 when unknown), `sort` in {`name`, `experience`, `newest`}, and `per_page`
  (1–50). Page-based pagination with a `meta` block.
- `GET /api/v1/consultants/{consultant}` — public detail; returns **404** unless
  the consultant is discoverable, so non-public profiles are indistinguishable
  from missing ones.
- Public resources expose only public information (no user id, email, phone, or
  `user_id`). Private data is never serialised on these endpoints.

Consultant self-management (`auth:sanctum` + `role:consultant`, prefix
`/api/v1/consultant`):

- `GET|PUT /profile` — own profile. `GET` returns 404 until the profile exists;
  `PUT` provisions it on first write (`firstOrNew`/`firstOrCreate`) and always
  answers **200** — a resource wrapping a just-created model would otherwise
  answer 201 (Laravel `ResourceResponse` uses `wasRecentlyCreated`).
- `PUT /specialties` — replaces the consultant's specialty set (validated ids,
  synced).
- `GET|POST /consultation-types`, `PUT|DELETE /consultation-types/{id}` — own
  consultation types. `POST` answers 201; the owner view additionally exposes
  `is_active` and `sort_order`.
- `GET|POST /availability`, `PUT|DELETE /availability/{id}` — own availability
  rules (weekly, per weekday). `POST` answers 201; the owner view additionally
  exposes `id` and `is_active`. Overlapping active rules on the same weekday are
  rejected (422, `start_time`), enforced again server-side regardless of the
  client.
- Authorization uses `App\Policies\ConsultationTypePolicy` and
  `App\Policies\AvailabilityRulePolicy` (owner-only) plus the role middleware;
  another consultant's resource resolves to 404.

List responses use the shared envelope (`data` + `meta`) and the same error
shape as the rest of the API, so the frontend maps errors uniformly.

### 7.7 Availability slots and appointment booking

Implemented in Phase 6. Availability-rule CRUD lives in §7.6.

Public (no authentication):

- `GET /api/v1/consultants/{consultant}/slots` — free bookable starts for one
  consultation type over a Tehran date range. Required: `consultation_type`
  (must be an active type owned by the consultant, otherwise 404). Optional:
  `from`, `to` (`Y-m-d`, default today, window capped at `booking.max_range_days`
  = 31 → 422 beyond). The response is a `data` + `meta` envelope: `data` is a
  list of `{date, weekday, slots[]}` (only days with free slots), each slot
  `{starts_at, ends_at, label}` (UTC ISO-8601 plus a Tehran `H:i` label). No
  appointment counts or client identities are ever exposed.

Authenticated (`auth:sanctum`):

- `POST /api/v1/appointments` — book a slot (`role:client`, `throttle:booking`,
  20/min per user). Body: `consultation_type_id`, `starts_at` (UTC instant),
  optional `notes`. Answers 201 with the participant view of the appointment.
  A time that is off the availability grid, already past, or that targets an
  inactive consultant/type is rejected with **422**; a grid slot already held by
  an active appointment is rejected with **409** `booking_conflict` (see §9).
- `GET /api/v1/appointments/{appointment}` — participant view. Non-owners get
  **404** rather than 403 so the endpoint cannot be used as an existence oracle
  (no IDOR).
- `POST /api/v1/appointments/{appointment}/cancel` — participant cancel with an
  optional reason, subject to `booking.cancellation_lead_hours` for clients.
- `POST /api/v1/appointments/{appointment}/reschedule` — client-only, subject to
  `booking.reschedule_lead_hours`, re-validating the new slot atomically.

All appointment responses include a `can` block (`cancel`, `reschedule`) derived
from `App\Policies\AppointmentPolicy` for the requesting user, so the UI never
has to guess permissions.

---

## 8. Domain Model Overview

Implemented in Phase 3 (migrations `2026_10_16_1600xx`). All timestamps are UTC.

Entities:

- `users` — base Laravel table plus `role` enum(`client`,`consultant`,`admin`),
  unique nullable `phone`, `avatar_path`, `is_active`. `role` casts to
  `App\Enums\UserRole`.
- `specialties` — `name`, unique ASCII `slug`, `description`, `sort_order`.
  Curated list in `App\Support\SpecialtyCatalog` (shared by factory and seeder).
- `consultant_profiles` — 1:1 with a consultant `user`; `headline`, `bio`,
  `years_experience`, `is_verified`, `is_active`, `verified_at`. Scopes
  `active` and `discoverable` (active + verified).
- `consultant_specialty` — composite-PK pivot (no timestamp columns; relations
  must **not** use `withTimestamps()`).
- `consultation_types` — per profile: `title`, `description`,
  `duration_minutes`, `price_toman`, `is_active`, `sort_order`.
- `availability_rules` — per profile: `weekday` (`0` = Sunday … `6` = Saturday,
  matching Carbon `dayOfWeek`), `start_time`/`end_time` as `HH:MM` strings,
  `is_active`.
- `appointments` — `client`, `consultant_profile`, `consultation_type`;
  `starts_at`/`ends_at` (UTC); `status` enum; `price_toman` snapshot; `notes`;
  cancellation fields; derived nullable-unique `active_slot_key`.
- `notifications` — the Laravel database-notifications table (uuid id, morph
  `notifiable`, `data`, `read_at`). `audit_events` is deferred to Phase 8.

Constraints and indexes:

- `appointments.active_slot_key` is **unique** — the mechanism behind the
  booking guarantee in Section 9. It is derived in an `Appointment` `saving`
  hook for active statuses and set to `NULL` otherwise, so terminal rows never
  collide (MySQL/MariaDB allow multiple `NULL`s in a unique index).
- Appointment foreign keys are `RESTRICT` on delete (history is preserved);
  `cancelled_by_id` is `SET NULL`; `consultant_profiles.user_id` is `CASCADE`.
- Indexes: `appointments(consultant_profile_id, starts_at)`,
  `appointments(client_id, starts_at)`, `appointments(status)`;
  `consultation_types(consultant_profile_id, is_active)`;
  `availability_rules(consultant_profile_id, weekday)`;
  `consultant_profiles(is_active, is_verified)`; `users(role)`.

Status machine (`App\Enums\AppointmentStatus`):

- `pending → {confirmed, cancelled}`; `confirmed → {completed, cancelled}`;
  `completed` and `cancelled` are terminal (`canTransitionTo()` encodes this).
  Actual transition enforcement lives in the Phase 6 booking/cancellation
  services; the enum is the single source of truth.

Seeders:

- `SpecialtySeeder` — idempotent (`firstOrCreate` on `slug`).
- `DemoDataSeeder` — fictional Persian data only: 1 admin, 6 consultants (each
  with profile, specialties, 2 consultation types, weekly availability), 12
  clients, and 48 appointments (12 per status). Emails use `example.com/net/org`
  and a guard skips the seeder when demo data already exists. Passwords use the
  development value `password` and must never be used outside local development.

---

## 9. Booking Concurrency & Integrity Design

Goal: two clients must never hold the same slot for the same consultant.
Implemented in Phase 6 in `App\Services\Booking\BookingService` (with
`App\Services\Booking\SlotGenerator` for slot derivation).

Design (MariaDB-compatible):

1. **Unique active-slot key.** `appointments.active_slot_key` is a stored /
   computed string such as `{consultant_profile_id}|{starts_at_utc}` when the
   status is `pending` or `confirmed`, and `NULL` otherwise. A **unique index**
   on this column enforces one active appointment per consultant per slot, while
   cancelled/completed rows (`NULL`) do not collide (MySQL/MariaDB allow
   multiple `NULL`s in a unique index).
2. **Transaction boundary.** Booking, rescheduling, and cancellation run inside
   `DB::transaction(fn () => ..., attempts: 5)`, which also retries on deadlock.
3. **Row locking.** Inside the transaction the consultant profile row (and, for
   cancel/reschedule, the appointment row) is read with `SELECT ... FOR UPDATE`
   so concurrent bookings for the same consultant serialize before the insert.
   This is a no-op on SQLite, so the everyday suite exercises the conflict
   *path*; genuine cross-process parallelism is covered by the opt-in MariaDB
   test in `tests/Feature/Booking/RealDatabaseBookingConcurrencyTest.php`
   (see §14.1).
4. **Conflict handling.** A duplicate-key violation on `active_slot_key`
   (SQLSTATE `23000` / driver error `1062`) is translated by
   `App\Exceptions\BookingConflictException` into a **409 Conflict** response
   with a `booking_conflict` error code and a clear Persian message; the client
   re-fetches availability.
5. **Status state machine.** Allowed transitions (`pending → confirmed →
   completed`, `pending/confirmed → cancelled`) are validated in
   `BookingService` (`assertActive`) and covered by automated tests.

This satisfies the "prevent concurrent double-booking" requirement without
PostgreSQL-specific features.

---

## 10. Timezone & Persian Localization Policy

- **Storage timezone**: `APP_TIMEZONE=UTC`. All timestamps written to the
  database are UTC. This is the decision confirmed at the end of Phase 2 after
  the conflict below was identified.
- **Presentation timezone**: `Asia/Tehran` (`HealthController::DISPLAY_TIMEZONE`
  and the frontend `Intl` formatters). Nothing is persisted in Tehran time.
- **Presentation**: converted to Tehran time and displayed with the **Jalali**
  calendar on the frontend (`src/lib/format.ts`).
- Slots are defined in Tehran local time for the user; the application converts
  explicitly at the boundary (Tehran ⇄ UTC) using `timezonedb`-free arithmetic
  (Iran has no DST since 2022, but conversions still go through `Carbon` with an
  explicit timezone rather than manual offsets).
- Persian digits and grouping via `Intl` (frontend) and `intl`/`NumberFormatter`
  (backend); phone numbers and prices (Toman) formatted per locale `fa-IR`.
- All user-facing strings are Persian; validation and error messages are
  localized (Persian language files).

### 10.1 Background — conflict identified and resolved (Phase 2)

The original Phase 1 wording paired an application timezone of `Asia/Tehran`
with a UTC-persistence rule. Those cannot coexist: Laravel serializes
Eloquent timestamps using the application timezone, so `APP_TIMEZONE=Asia/Tehran`
would have stored Tehran-local time. Phase 2 fixed `config/app.php` (the
skeleton hardcoded `'timezone' => 'UTC'`, ignoring `APP_TIMEZONE`) and the
conflict was surfaced before any appointment column existed.

Resolution (user-approved, end of Phase 2): **store UTC, convert for display.**
`APP_TIMEZONE=UTC`; the health endpoint reports both `time_utc` and
`time_tehran`, and `display_timezone` documents the presentation zone.

---

## 11. Frontend Architecture (React + TypeScript)

- **Structure**: feature-oriented folders; presentational components separated
  from data-fetching hooks.
- **Routing**: React Router; public (discovery, booking) vs authenticated
  (dashboards) routes; role-guarded areas.
- **Server state**: TanStack Query for caching, retries, and mutation states;
  no ad-hoc global fetch state.
- **Client state**: minimal; local component state + small context where needed.
- **Forms**: react-hook-form + Zod schemas mirroring backend validation messages.
- **API layer**: a single typed HTTP client; TypeScript types mirror the API
  contract; no `any` in public interfaces.
- **States**: every data view handles loading, empty, success, and error states.
- **Accessibility**: keyboard navigation, visible focus, `aria-expanded` for
  accordions, labeled inputs, sufficient contrast.
- **RTL**: `<html lang="fa" dir="rtl">`; spacing/sizing via logical properties
  (`ms/me`, `ps/pe`, `start/end`); directional icons mirrored; focus order
  follows RTL.

---

## 12. Design System Tokens (adapted from `sample.html`)

`sample.html` (a Framer "Haumind" wellness template) is the visual reference.
Its LTR, Latin-typography specifics are adapted to a Persian RTL product.

Palette:

| Token | Value | Use |
| --- | --- | --- |
| `--bg` | `#121313` | App background (dark) |
| `--surface` | `rgba(255,255,255,0.06)` / `0.10` | Cards, FAQ rows |
| `--text` | `#ffffff` | Primary text |
| `--text-muted` | `rgba(255,255,255,0.7)` / `0.5` | Secondary/tertiary text |
| `--accent` | `#7be1d4` | Primary accent (mint) |
| `--accent-strong` | `#45b0a2` | Hover/active accent |
| `--accent-deep` | `#31978a` | Deep accent |
| `--border` | `rgba(255,255,255,0.14)` | Dividers, outlines |

Geometry and type:

- Radii: `8px` (primary), `12px`, `20px`, `100%` (avatars).
- Type scale (adapted): body 14/18, 16, 18, 20/24, 24/32, 36/40, 44, 60, 72 —
  with looser line-heights for Persian glyphs (~1.6 body, ~1.35 display).
- Typography: **Vazirmatn** (UI/body) plus a Persian display face for headings,
  replacing the Latin `Geist` / `Playfair Display` of the reference.
- Imagery: reference assets (`framerusercontent.com`) are **not** hotlinked in
  production; licensed/local assets are used instead.
- Motion: hover `filter/color` transitions (~0.2s), scroll-reveal fades, and
  accordion expansion — reimplemented natively, not via the Framer runtime.

---

## 13. Security Baseline

- Server-side validation for all input; never trust the client.
- Server-side authorization on every protected endpoint (Policies/Gates).
- Protection against SQL injection (Eloquent/query builder bindings),
  XSS (escaped output, no raw HTML injection), mass assignment (`$fillable`),
  IDOR (scoped queries), and broken access control.
- Rate limiting on authentication and other sensitive endpoints.
- Secrets via `.env` only; `.env` is never committed; `.env.example` documents
  required keys with placeholder values.
- Dedicated least-privilege database user for the application (created in Phase 2
  with approval) — never `root`.
- File uploads (future avatar support) validated by type/size and stored outside
  the web root.
- No sensitive content in logs or API responses.

---

## 14. Testing Strategy

- **Backend**: feature tests for auth, authorization, discovery, booking
  (including **concurrent double-booking**), cancellation/rescheduling, and
  admin restrictions; unit tests for booking rules and status transitions.
- **Frontend**: component tests for forms, validation, RTL rendering, and
  critical flows with Vitest + Testing Library.
- **Static gates**: `php` lint / Pint, TypeScript `tsc --noEmit` (via
  `npm run build`), oxlint, and a production build must pass.
- Migrations are verified against MariaDB.
- Local checks (from `backend/` and `frontend/` respectively):
  `php artisan test`, `vendor/bin/pint --test`, `npm run build`, `npm run lint`.

### 14.1 Test database

`phpunit.xml` runs the whole suite against an **in-memory SQLite** database. This
is deliberate: as shipped, the skeleton's `DB_CONNECTION`/`DB_DATABASE` test
values were commented out, so `RefreshDatabase` would have run against the
development MariaDB database and dropped its tables. This was corrected in
Phase 3.

SQLite cannot exercise MariaDB-specific behaviour (row locks, `FOR UPDATE`,
unique-index races). A **dedicated MariaDB test database**,
`consultation_platform_test`, was therefore created (with the owner's explicit
approval) and granted to the existing least-privilege `consultation_app` user:

```sql
CREATE DATABASE consultation_platform_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON consultation_platform_test.*
    TO 'consultation_app'@'localhost';
```

The genuinely-parallel booking test lives in
`backend/tests/Feature/Booking/RealDatabaseBookingConcurrencyTest.php`, driven by
the plain worker script `backend/tests/Support/booking_concurrency_worker.php`.
It is **opt-in and self-guarding** so a normal `php artisan test` run never
depends on MariaDB:

- It skips unless `RUN_MARIADB_CONCURRENCY=1` is set.
- It skips if the dedicated database is unreachable.
- It hard-asserts the connected database name is the test database (never the
  development database) and that the driver is MariaDB/MySQL.
- It uses **no `RefreshDatabase`**: the two worker processes must see committed
  fixtures and each other's writes, so the test runs `migrate:fresh` (on the
  dedicated test database only) and cleans up its rows in `tearDown`.

Run it with:

```powershell
$env:RUN_MARIADB_CONCURRENCY='1'
php artisan test --filter=RealDatabaseBookingConcurrencyTest
```

Two OS processes boot the real application and POST the same slot through the
HTTP kernel simultaneously; the test asserts the outcome is exactly one `201`
and one `409` (`booking_conflict`) with a single active row left behind.

> The dedicated test database is **never** dropped. `migrate:fresh` recreates
> tables only *inside* `consultation_platform_test`; the development database
> (`consultation_platform`) is untouched.

---

## 15. Configuration & Secrets

- Backend `.env` keys (non-secret defaults shown): `APP_NAME`, `APP_ENV`,
  `APP_URL=http://127.0.0.1:8000`, `APP_LOCALE=fa`, `APP_FAKER_LOCALE=fa_IR`,
  `APP_TIMEZONE=UTC` (storage; presentation converts to `Asia/Tehran`), `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`,
  `DB_PORT=3306`, `DB_DATABASE=consultation_platform`,
  `DB_USERNAME=consultation_app`, `DB_PASSWORD` (secret, dedicated app user),
  `SESSION_DRIVER=database`, `FRONTEND_URL=http://127.0.0.1:5173`.
- Frontend `.env`: `VITE_API_BASE_URL` (defaults to
  `http://127.0.0.1:8000/api/v1` in code if unset).
- CORS (`config/cors.php`): `api/*` is restricted to `FRONTEND_URL`.
- No credentials or tokens are committed or printed. `.env` is git-ignored in
  both applications; only `.env.example` files are tracked.

---

## 16. Phase 1 Changes Applied

Environment configuration performed (all reversible):

1. `C:\xampp\php\php.ini` backed up to
   `C:\xampp\php\php.ini.bak-phase1-20261016` (SHA-256 verified identical).
2. Three lines enabled in `php.ini`: `extension=intl`, `extension=gd`, and
   `extension=zip` (all DLLs already present; `zip` is required by Composer).
   Effective for the PHP CLI immediately; Apache
   (mod_php) adopts them on its next restart, which is controlled by the user.
   **No Apache or MySQL restart was performed.**
3. Composer 2.10.3 installed portably at `C:\tools\composer\composer.phar` with a
   `composer.bat` wrapper (calls XAMPP PHP). SHA-256 verified against the
   published checksum. `C:\tools\composer` was appended to the **user** PATH
   (append-only).

Not performed: no database creation, no schema changes, no application code, no
service restarts, no modification of `sample.html`.

### 16.1 Phase 2 Changes Applied (2026-10-16)

Application code and tooling created (all reversible):

1. **Backend** `backend/`: Laravel 12.70.0 (`composer create-project`), Sanctum
   installed via `install:api`. `routes/api.php` under prefix `api/v1`.
2. **API contract foundation**: `HealthController` (`GET /api/v1/health`);
   JSON rendering and envelope-shaped validation errors in `bootstrap/app.php`;
   `config/cors.php` restricted to `FRONTEND_URL`.
3. **Timezone fix**: `config/app.php` `timezone` now reads `APP_TIMEZONE`
   instead of the hardcoded `UTC`. The resulting conflict (Section 10.1) was
   resolved by setting `APP_TIMEZONE=UTC` and converting to `Asia/Tehran` only
   for presentation.
4. **Frontend** `frontend/`: Vite 8 + React 19 + TypeScript 6; Tailwind 4 theme
   tokens, RTL `html`, Vazirmatn font, typed API client, TanStack Query, React
   Router, and a `HomePage` demonstrating the tokens and the health state.
5. **Repository**: Git initialized at the workspace root; root `.gitignore`
   added; `.env` files, `vendor/`, and `node_modules/` excluded.
6. **Database**: `consultation_platform` (utf8mb4) and a dedicated
   least-privilege user `consultation_app` (created in Phase 2 with approval);
   the default `test` database remains visible to that user (not revoked).

No Apache or MySQL restart was performed. Only `php.ini` (Phase 1 backup
retained) was modified outside the workspace.

### 16.2 Phase 3 Changes Applied (2026-10-16)

1. **Migrations** (`2026_10_16_160000`–`160700`): users role/profile fields;
   `specialties`; `consultant_profiles`; `consultant_specialty`; 
   `consultation_types`; `availability_rules`; `appointments`;
   `notifications`. All applied to MariaDB, then rollback and `migrate:fresh`
   were exercised successfully.
2. **Enums**: `App\Enums\UserRole`, `App\Enums\AppointmentStatus` (with
   `isActive()` / `canTransitionTo()`).
3. **Models**: `User` (role cast, relations, `isAdmin/isConsultant/isClient`),
   `Specialty`, `ConsultantProfile`, `ConsultationType`, `AvailabilityRule`,
   `Appointment` (derived `active_slot_key` via a `saving` hook).
4. **Factories**: `SpecialtyFactory`, `ConsultantProfileFactory`,
   `ConsultationTypeFactory`, `AvailabilityRuleFactory`, `AppointmentFactory`;
   `UserFactory` gained `role()` / `inactive()` states.
5. **Seeders**: `SpecialtySeeder`, `DemoDataSeeder`, wired in `DatabaseSeeder`.
6. **Test database fix** (Section 14.1): `phpunit.xml` set to in-memory SQLite
   so tests cannot touch the development database.
7. **Tests added**: `AppointmentStatusTest` (unit),
   `ConsultantProfileRelationshipTest`, `AppointmentSlotTest`,
   `DemoDataSeederTest` — 24 tests / 115 assertions passing.

Decisions:

- Weekday convention `0` = Sunday … `6` = Saturday (Carbon `dayOfWeek`).
- `availability_rules` times are stored as `HH:MM` strings (portable and simple
  to validate) rather than SQL `TIME`.
- Appointment monetary values use unsigned integer Toman; `price_toman` on an
  appointment is a snapshot of the consultation type price at booking time.
- Booking concurrency remains the unique `active_slot_key` design (Section 9);
  a MariaDB-backed concurrency test is deferred to Phase 6/10.

### 16.3 Phase 4 Changes Applied (2026-10-16)

1. **Auth endpoints** (`routes/api.php`): `POST /auth/register`,
   `POST /auth/login`, `GET /auth/me`, `POST /auth/logout`,
   `PUT /auth/profile`, `PUT /auth/password`; protected by `auth:sanctum`.
2. **`AuthController`** with `App\Http\Requests\Auth\{Register,Login,UpdateProfile,ChangePassword}Request`
   and `App\Http\Resources\UserResource`.
3. **Authorization primitives**: gates `access-admin` / `access-consultant`;
   `role` middleware alias (`EnsureUserHasRole`).
4. **Rate limiters** and the password policy registered in `AppServiceProvider`.
5. **Guest-redirect fix**: unauthenticated `api/*` requests now always get 401
   JSON; previously a request without `Accept: application/json` crashed with
   "Route [login] not defined" (HTTP 500).
6. **Localization**: `lang/en` published; `lang/fa` added (`auth`, `passwords`,
   `pagination`, `validation`) so auth/validation errors are Persian.
7. **Tests**: `RegistrationTest`, `LoginTest`, `AuthorizationTest`,
   `PasswordChangeTest` + a shared `Tests\Concerns\AssertsApiValidation` trait.
   `Tests\TestCase` now clears auth guards after each request to reproduce real
   per-request behaviour (Sanctum's `RequestGuard` caches the user).

Notes:

- `UserFactory` gained `client()`, `consultant()`, `admin()` states.
- Verified live: register/login/me/logout flow, 401 JSON without `Accept`,
  429 after repeated failed logins, Persian validation messages, and CORS.

Decisions:

- Invalid credentials return 422 with one generic message (no enumeration);
  a suspended account returns 403 only after the password is verified.
- API error messages are Persian (`lang/fa`); API `code`/`field` data remains
  English so the frontend can map errors without depending on wording.

### 16.4 Phase 5 Changes Applied (2026-10-16)

1. **Public discovery endpoints** (`routes/api.php`): `GET /specialties`,
   `GET /consultants`, `GET /consultants/{consultant}` via
   `Api\V1\{SpecialtyController, ConsultantController}` and
   `App\Http\Requests\Discovery\ConsultantIndexRequest`.
2. **Consultant self-management endpoints** (prefix `/consultant`,
   `auth:sanctum` + `role:consultant`): `ConsultantProfileController`,
   `ConsultantSpecialtyController`, `ConsultantConsultationTypeController`,
   `ConsultantAvailabilityController` with the matching FormRequests.
3. **Resources**: public `SpecialtyResource`, `ConsultationTypeResource`,
   `AvailabilityRuleResource`, `ConsultantSummaryResource`,
   `ConsultantDetailResource`; owner `ConsultantProfileResource`,
   `ConsultantConsultationTypeResource` (adds `is_active`/`sort_order`).
4. **Policy**: `App\Policies\ConsultationTypePolicy` (owner-only), authorized
   with `Gate::authorize`.
5. **Frontend**: consultants feature (types/api/hooks/card/filters), public
   `ConsultantsPage` and `ConsultantDetailPage`, auth context/provider/guard and
   `LoginPage`, app shell + routing, consultant dashboard feature, and the UI
   primitives in `frontend/src/components/ui`. All Persian and RTL.
6. **Tests**: `ConsultantDiscoveryTest`, `ConsultantProfileManagementTest`,
   `ConsultationTypeManagementTest`.

Decisions:

- Enforcement of "discoverable" (active **and** verified) happens in the query
  scopes and the detail lookup, so non-public consultants 404 rather than 403.
- Owner views and public views use separate resources; public endpoints never
  serialise `user_id`, email, or phone.
- Availability-rule **management** is intentionally deferred to Phase 6 (where
  slot generation lives); Phase 5 exposes a read-only list on the dashboard.

Notes:

- Public listing supports `q`, `specialty`, `sort`, and `per_page` (1–50);
  unknown values fail validation with 422 rather than being silently ignored.
- The frontend build reports a single ~587 kB (≈183 kB gzip) entry chunk; code
  splitting is a Phase 11 polish item, not a correctness issue.

### 16.5 Phase 6 Changes Applied (2026-10-16)

1. **Booking config** `backend/config/booking.php`: `timezone=Asia/Tehran`,
   `max_range_days=31`, `default_range_days=7`, `minimum_lead_minutes=5`,
   `cancellation_lead_hours=2`, `reschedule_lead_hours=2`.
2. **Availability-rule CRUD** (`routes/api.php`, prefix `/consultant`):
   `POST /availability` (201), `PUT|DELETE /availability/{availabilityRule}`,
   with `Store|UpdateAvailabilityRuleRequest` (weekday 0–6, `H:i`, end > start,
   overlap guard) and owner-only `ConsultantAvailabilityRuleResource`, plus
   `App\Policies\AvailabilityRulePolicy`.
3. **Slot generation** `App\Services\Booking\SlotGenerator`: candidate starts are
   the consultation-type duration stepping from the rule `start_time` and must
   fit inside `end_time`; active appointments block their overlaps; a
   `minimum_lead_minutes` lead filters near-term starts; all boundary conversions
   go Tehran ⇄ UTC through Carbon. Exposed publicly via
   `ConsultantController::slots` + `ConsultantSlotsRequest` (§7.7).
4. **Booking/reschedule/cancel** `App\Services\Booking\BookingService`: atomic
   (`DB::transaction(..., 5)`), locks the consultant profile (and appointment),
   validates the slot against availability, and maps duplicate-key violations to
   `App\Exceptions\BookingConflictException` (409). See §9.
5. **Appointments API** (`auth:sanctum`): `POST /appointments` (`role:client`,
   `throttle:booking` 20/min), `GET /appointments/{appointment}`,
   `POST /appointments/{appointment}/cancel`,
   `POST /appointments/{appointment}/reschedule` (`role:client`). Participant view
   is `AppointmentResource`; authorization is `App\Policies\AppointmentPolicy`;
   non-owners resolve to 404 (no IDOR).
6. **Rate limiter** `booking` (20/min per user or IP) added in
   `AppServiceProvider`.
7. **Frontend** (Persian, RTL): `features/booking` (types/api/hooks),
   `BookingPanel` (consultation-type + day + slot picker, optional notes),
   `AppointmentDetailPage` (`/appointments/:id` with cancel + reschedule),
   `RequireAuth` guard, and the booking CTA on `ConsultantDetailPage` (guests are
   sent to `/login` with a return path; non-clients see a clear notice). The
   read-only dashboard availability list was replaced by a full
   `AvailabilityEditor` (create/edit/delete, per-weekday, active toggle).
8. **Tests**: `AvailabilityManagementTest`, `SlotGenerationTest`,
   `AppointmentBookingTest`, `AppointmentLifecycleTest`.

Decisions:

- **409 vs 422.** "Already taken" (409 `booking_conflict`) is distinguished from
  "not a bookable time" (422), so the UI can prompt either re-selection or a
  hard error.
- **404 over 403 for appointment access.** Owners only; foreign appointments are
  reported as missing.
- **Slot grid = consultation-type duration.** A consultant offering different
  durations publishes a different slot grid per type; slots are never derived
  from the rule length alone.

Notes:

- Iran is UTC+03:30 year-round (no DST since 2022), so 09:00 Tehran is
  `05:30Z`; tests assert this explicitly.
- `php artisan test` = **105 passed / 482 assertions**; `pint --test` clean;
  frontend `npm run build` and `npm run lint` pass (one pre-existing
  `set-state-in-effect` warning in `AuthProvider`).
- Live API smoke (18 checks) against the running MariaDB stack passed, including
  the 409 duplicate-book path; the seeded database was left unchanged.
- **Real MariaDB concurrency test added** (§14.1):
  `RealDatabaseBookingConcurrencyTest` + `booking_concurrency_worker.php`. Run
  with `RUN_MARIADB_CONCURRENCY=1 php artisan test --filter=RealDatabaseBookingConcurrencyTest`
  → **3 passed / 26 assertions** against `consultation_platform_test` (two
  simultaneous cross-process bookings yield one `201` + one `409`
  `booking_conflict`; the unique index backstop and the cancelled-slot case also
  pass). On a normal `php artisan test` run the three cases skip cleanly
  (`105 passed, 3 skipped`), and `pint --test` is clean.
  The development database was verified untouched (19 users / 48 appointments)
    and the dedicated test database was left with zero test rows.

---

### 16.6 Phase 7 Changes Applied (2026-10-16)

1. **Listing API** (`auth:sanctum`, routes/api.php): `GET /api/v1/appointments`
   (`api.v1.appointments.index`) with `AppointmentIndexRequest` (`scope`
   `upcoming|past|all`, optional `status` enum, `per_page` 1–50, `page` 1+).
   Role-aware listing via `App\Models\Appointment::scopeForParticipant`
   (`client_id` for clients, their consultant-profile bookings for consultants,
   everything for admins — no separate role middleware on the route). Scopes:
   `upcoming` = active (pending/confirmed) appointments starting at or after now,
   soonest first; `past` = terminal status **or** start time already passed,
   newest first; `all` = everything ordered by `starts_at` descending, all over
   `AppointmentResource::collection(...->paginate(...)->withQueryString())`.
2. **Status transition API**: `POST /api/v1/appointments/{appointment}/status`
   (`api.v1.appointments.status`) with `UpdateAppointmentStatusRequest`
   (`Rule::in(['confirmed','completed'])`). Guarded by
   `AppointmentPolicy::updateStatus` (consultant owner or admin, active
   appointment only). Illegal transitions (e.g. `pending → completed`) produce a
   422 with a Persian `status` message via `ValidationException`.
3. **Model scopes** (`App\Models\Appointment`): `scopeForParticipant`,
   `scopeUpcoming`, `scopePast`, and `activeStatusValues()` (pending/confirmed
   filter used by the unique-slot-key state machine already documented in §9).
4. **`AppointmentResource::can` block** extended with `confirm` (only when
   `status === 'pending'` **and** policy passes) and `complete` (only when
   `status === 'confirmed'` **and** policy passes) alongside the existing
   `cancel`/`reschedule` entries, so the UI can render actions per role.
5. **Frontend** (Persian, RTL):
   - `features/booking`: `Appointment.can.confirm|complete` types,
     `APPOINTMENT_SCOPES`/`APPOINTMENT_SCOPE_LABELS`, `fetchAppointments` +
     `updateAppointmentStatus` (pagination via the shared `apiRequestPage`),
     `appointmentsQueryKey`/`useAppointments` (`keepPreviousData`) and
     `useUpdateAppointmentStatus` (sets the detail cache and invalidates the
     list; `create|cancel|reschedule` mutations now also invalidate
     `['appointments']`); shared `AppointmentCard` used by every list.
   - `pages/AppointmentsPage.tsx` at `/appointments`: URL-driven scope tabs and
     `?page=` pagination (`useSearchParams`, `replace: true`), skeleton/error
     (retry StateMessage)/empty states.
   - `features/profile`: `ProfileForm` (name + optional phone, `PUT
     /auth/profile`, re-synced `useAuth().refresh` after save), `PasswordForm`
     (current + new + confirmation with the same zod rules as registration, `PUT
     /auth/password`), and `pages/ProfilePage.tsx` at `/profile`. Both routes are
     wrapped in `RequireAuth`; the API revokes every other token on password
     change (AuthController keeps the current one).
   - `features/consultant-dashboard/AppointmentsManager.tsx` on `/dashboard`:
     upcoming/past tabs, uses the same `AppointmentCard` with `showClient`,
     inline two-step confirm/complete actions on the fixed `can` flags, 409-aware
     error message, and a link to the appointment detail page.
   - `AppShell` nav gains `نوبت‌های من` (clients) and `پروفایل` (every
     authenticated user).
6. **Tests**: `AppointmentListingTest` (11 / 52 assertions), 
   `AppointmentStatusTransitionTest` (10 / 33 assertions).

Notes:

- A test-harness quirk was documented in the tests: `TestCase::call()` calls
  `forgetGuards()` after every request, so a follow-up request in the same test
  must re-authenticate (`Sanctum::actingAs`) or it fails with 401.
- Slot-counting in tests: a 09:00–12:00 rule with a 30-minute type yields
  exactly 6 candidate slots (indexes 0–5).
- Checks executed: `php artisan test` (**126 passed, 3 skipped, 567
  assertions**; with the concurrency flag **129 passed**), `pint --test`
  passed (after it fixed three style issues in `AppointmentPolicy`),
  frontend `npm run build` passed and `npm run lint` passed (the same
  pre-existing `set-state-in-effect` warning in `AuthProvider`; the production
  ~620 kB entry chunk — the single ~620 kB bundle remains a Phase 11
  code-splitting item). A user-directed **visual foundation** interlude is
  recorded in the `IMPLEMENTATION_PLAN.md` Visual Foundation Addendum
  (2026-10-17): redesign tokens, shared `Container`/`Section`/`Reveal`/`Header`
  /`Footer`/`Logo` components, a rebuilt role-aware header with mobile drawer,
  footer, and a `HomePage` hero using locally bundled reference photography —
  no API or route changes.

---

## 17. Risks, Trade-offs & Revisit Triggers

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Laravel 12 is in security-fix support until Feb 2027 | Framework eventually unsupported | Document; upgrade PHP to ≥ 8.3 to move to Laravel 13 when feasible |
| MariaDB 10.4 lacks `SKIP LOCKED` | Weakens some locking patterns | Unique-key + transaction + retry design (Section 9) |
| MariaDB 10.4 is old | Missing newer server features | Revisit if requirements grow; schema kept portable |
| mod_php uses a different extension set than CLI until restart | Subtle behaviour difference via Apache | Development uses `artisan serve`; document Apache restart requirement |
| XAMPP shares one PHP/MariaDB instance with other projects | Changes could affect `akhavan.local` | Never restart without approval; isolated new database and app user |

---

## 18. Open Items / Next Phase

Phase 2 is complete (see Section 16.1 and the completion record in
`IMPLEMENTATION_PLAN.md`). Phase 3 (schema and domain models) is complete (see
Section 16.2 and Section 8). Phase 4 (authentication and authorization) is
complete (see Sections 7.2, 7.3, 7.5 and Section 16.3). Phase 5 (consultant
profiles and discovery) is complete (see Sections 7.6 and 16.4). Phase 6
(availability and appointment booking) is complete (see Sections 7.7, 9 and
16.5). Phase 7 (client and consultant dashboards) is complete (see Section 16.6;
appointment listing/status-transition API plus the Persian dashboards).

Open items carried forward:

- Optionally revoke the default `test` database visibility from
  `consultation_app` (least-privilege tidy-up).
- The dedicated MariaDB **test** database (`consultation_platform_test`,
  Section 14.1) now exists and backs the opt-in `RealDatabaseBookingConcurrencyTest`.
  It is created and migrated but never dropped automatically.
- `audit_events` (administrative audit log) is deferred to Phase 8.
- Password reset / email verification are not implemented yet (email delivery is
  out of scope until a later phase); document before exposing account recovery.
- The next phase is Phase 8 (administration dashboard): administrative users and
  role management, consultant profile/specialty management, appointment
  monitoring, operational summaries, and the `audit_events` log.
- No blockers remain for starting Phase 8.

---

## 19. Website Completion Task — Architecture Decisions (2026-10-18)

User-approved work that extended the platform beyond the originally scheduled
phases is recorded here so the decisions stay discoverable.

### 19.1 Consultation mode

`consultation_types.mode` (`online` | `in_person`, default `in_person`) and a
nullable `appointments.mode` snapshot. The mode is copied onto the appointment
at booking time exactly like `price_toman`, and is immutable on reschedule, so a
past booking keeps the terms it was agreed under. The set of modes the platform
actually offers lives in one place: `App\Support\ConsultationModes::all()`
intersects the `supported_modes` site setting with the enum and falls back to
`config('booking.supported_modes')`. Consultants may only create offerings in a
supported mode; administrators may use any enum value.

### 19.2 Site settings

A minimal `site_settings` key/value table (`SiteSetting`) with a JSON-cast
`value`. It deliberately stores only operator-managed configuration (currently
`supported_modes` and `contact_lines`) rather than becoming a general-purpose
data bag; anything relational stays in its own table.

### 19.3 Reviews are verified-only, by construction

`consultant_reviews.appointment_id` is unique and the submission rules require
one of the author's own *completed* appointments with the consultant in the
route. "Verified" is therefore a schema property, not a UI claim, and no
anonymous reviews exist. Moderation uses a status column
(`pending|approved|rejected|hidden`); only `approved` rows are public, edits
return a review to `pending`, and hiding preserves the audit trail. Reviewer
identity is reduced to a display name (given name + surname initial) — never
ids, emails, or appointment details.

### 19.4 Active-account enforcement

`EnsureUserIsActive` is prepended to the `api` middleware group and is repeated
inside `EnsureUserHasRole`. Deactivating a user takes effect on the next request
instead of waiting for token expiry. Admin endpoints additionally re-check
`isAdmin()` inside each `FormRequest::authorize()`, so route middleware is never
the only gate.

### 19.5 Remaining open items after this task

- `audit_events` (administrative audit log) is still deferred.
- Email delivery, payments, and account recovery remain out of scope.
- One dev-database row (`consultant_profiles.id = 1`) still holds mojibake
  (`?` bytes) in its `headline` from an early seeding run; it is data, not code,
  and is left untouched pending approval to correct it.
- The frontend ships as a single ~700 kB entry chunk; code-splitting is deferred
  to the deployment phase.

## 20. Sora rebrand, bilingual API, and review reports (2026-10-19)

### 20.1 Internationalisation of the API (fa default, en via header)

- `app/Http/Middleware/SetRequestLocale` (`SetRequestLocale::class`, first in
  the `api` middleware group) negotiates the locale from `Accept-Language`
  against `['fa', 'en']`; with no usable header the app default is Persian
  (`config/app.php` default changed `en` → `fa`; `.env` already pinned
  `APP_LOCALE=fa`).
- All user-facing backend messages moved into translation files:
  `lang/{fa,en}/{booking,reviews,contact,admin}.php`, plus the existing
  `validation.php`. No Persian string literals remain under `app/`.
- Catalog data localizes without duplicating DB rows: `specialties` gained a
  nullable `name_en` column (backfilled for the eight catalog slugs;
  `SpecialtySeeder` idempotently refreshes catalog labels). `SpecialtyResource`
  returns the localized value as `name` and keeps the canonical Persian label
  as `name_fa` for every client.
- Known testing quirk: Symfony's test request factory always injects
  `Accept-Language: en-us,en;q=0.5`, so feature tests that must see Persian
  pass the header explicitly (`ApiLocalizationTest` pins fa/en per request).

### 20.2 Review reports (moderation support flow)

- `review_reports` table: `review_id` (cascade), `reporter_id` (cascade),
  free-text `reason`, status `open|resolved|dismissed`,
  unique `(review_id, reporter_id)` — one report per user per review.
- API: `POST /reviews/{review}/report` (auth + dedicated
  `throttle:review-reports` = 5/minute + 20/hour). Eligibility is enforced in
  `StoreReviewReportRequest`: only approved reviews, never the author's own,
  never twice. Admin: `GET /admin/review-reports?status=…` plus
  `POST /admin/review-reports/{id}/resolve|dismiss` — triage only marks the
  report; the review itself is moderated separately through
  `AdminReviewController`, and nothing is emailed.
- Frontend: server errors come back localized through `Accept-Language`
  (`reviews.*`, `admin.*` keys); reporting, duplicate prevention, triage
  (resolve/dismiss), and the 403 boundary for non-admins are covered by
  `ReviewReportTest` (9 tests).

### 20.3 Verification record (2026-10-19)

- Backend `php artisan test`: **194 passed (854 assertions)**, with the
  MariaDB concurrency suite enabled (`RUN_MARIADB_CONCURRENCY=1` — 0 skipped).
  `pint --test` passed after fixing the three new dirty files.
- Frontend: `tsc -b` clean, `npm run build` passed (single ~1.85 s build;
  chunk-size warning unchanged), `npm run lint` clean (previous
  `set-state-in-effect` warning no longer emitted).
- Live API smoke on the dev stack (MariaDB + `artisan serve` + Vite): 19/19
  checks — login flows, localized validation and domain errors per
  `Accept-Language`, specialty name localization, real booking (201),
  duplicate-booking conflict (409), consultant confirm/complete, review
  submit → admin approve → report submit → duplicate report 422 → admin
  resolve/dismiss, non-admin 403s, published-review integrity after triage.
- Visual verification: 60 headless-Chrome screenshots (2 languages × 2
  themes × 3 viewports × 15 pages). Every combination reports `overflow=0`
  with correct `lang`/`dir` (`fa`/`rtl`, `en`/`ltr`). Admin reports page,
  dashboards, and review surfaces confirmed against live data. Dashboard and
  admin list pages need ~7 s settle time under the dev stack before data
  renders (loading skeletons are the expected first paint).

## 21. Production-readiness mission: admin path, provisioning, bilingual profiles, dual currency, email verification, UI retheme (2026-10-19)

### 21.1 Private admin path (never exposed to the SPA bundle)

- The admin area lives at a server-validated path from `config/sora.admin_path`
  (env `ADMIN_PATH`; dev value kept only in `backend/.env`, documented in
  `.env.example` as a placeholder). `GET /{panel}` is handled by
  `AdminPanelController::show`, which compares with `hash_equals`, returns 404
  (plus `noindex`/`no-store`) when unset or mismatched, and 404s unconditionally
  for the literal `/admin`.
- The shell (`resources/views/admin-panel.blade.php`) injects
  `window.__ADMIN_BASE__` only on the correct path. Frontend
  `src/lib/adminBase.ts` reads it; the dev fallback `'/admin'` is behind
  `import.meta.env.DEV` and is dead-code-eliminated from the production bundle.
  Admin routes exist in `App.tsx` only when `adminBase() !== null`, so the SPA
  can never reveal the path in prod. All admin APIs stay `auth:sanctum`+`role`
  guarded; the public registration path can never mint admin/consultant roles.
- `vite.config.ts` now builds with `manifest: true` into
  `backend/public/build`, so the private shell serves the production bundle
  from the API origin; the public SPA deployment is unchanged.

### 21.2 No default credentials / first admin

- `php artisan admin:create` (one-time: refuses when any admin exists; prompts
  via `Laravel\Prompts`, never echoes the secret; password policy min 12 with
  mixed case + digits). `DatabaseSeeder` aborts with a `RuntimeException` in
  production; demo seeding is dev-only.

### 21.3 Consultant provisioning with one-time password setup links

- Admin `POST /admin/users/{user}/password-link` (consultant-only target,
  `throttle:admin-actions`, 10/min) returns a Password-broker setup URL exactly
  once; hashes/expiry handled by the broker. `AdminStoreUserRequest` made
  `password` optional: accounts are created with an unusable hash and
  `password_set_at = null` (`AdminUserResource` exposes `password_set`).
- Public `POST /auth/password-setup` (`PasswordSetupController`,
  `throttle:auth-password`) is single-use, sets `password_set_at`, revokes all
  Sanctum tokens, and answers with one generic message so user existence and
  throttle states are indistinguishable.

### 21.4 Bilingual consultant profiles (fa primary, en optional with fallback)

- `consultant_profiles` gained nullable `name_en`, `headline_en`, `bio_en`
  (migration `2026_10_19_000004`). `ResourceLocalization` resolves the EN value
  with the Persian fallback and never fabricates translations; public summary/
  detail resources localize name/headline/bio by `Accept-Language`.
- Admin resources expose the raw `*_en` fields plus a
  `translation_missing_en` list; the admin panel and each consultant dashboard
  profile form include dedicated English editors (max 160/4000 chars, LTR
  inputs). Historical/demo Persian data stays Persian-only (`?` mojibake in a
  few legacy rows predates this mission and was left untouched - see the
  report's data section).

### 21.5 Dual currency (toman primary, USD optional integer cents)

- Migration `2026_10_19_000005_add_usd_pricing`: nullable
  `consultation_types.price_usd_cents` and `appointments.price_usd_cents`
  (historical snapshots are never rewritten; new bookings may capture it).
  USD is entered manually (admin panel, dollars -> integer cents; consultants
  may keep their own optional value). No exchange rate is invented anywhere;
  EN surfaces show USD when present and fall back to toman otherwise.
- English-locale bookings are rejected with a localized
  `booking.usd_price_required` error when the session type has no USD fee
  (`BookingService::assertBookingCurrency`); the frontend mirrors the rule in
  `BookingPanel` (disabled submit + inline notice). Admin pricing flags
  missing USD fees.
- Testing insight (recorded for posterity): Symfony's test client always sends
  `Accept-Language: en-us,en;q=0.5`, so the booking feature tests that exercise
  the Persian flow pin `Accept-Language: fa` in their setup
  (+ the concurrency worker script does the same).

### 21.6 Email verification (single-use, expiring, throttled)

- `users.email_verified_at` existed in the base schema; new migration
  `2026_10_19_000006` adds `email_verification_tokens` (PK `user_id`, cascade,
  SHA-256 `token_hash`, expiring). Fresh registrations start unverified; the
  `VerifyEmailNotification` mail (mail channel; log driver locally, SMTP via
  `MAIL_*` only) carries a frontend `/verify-email?id&token&expires` link.
- Public `GET /auth/email/verify` is single-use (token row consumed) with one
  generic invalid-token message; authenticated `POST /auth/email/resend`
  (throttle `auth-email`, 3/min) is idempotent for verified users. Booking
  requires a verified email (`BookingService::assertEmailVerified`, localized
  error); `UserResource` exposes `email_verified`.
- Live E2E on the dev stack (log driver): registration -> link in
  `storage/logs/laravel.log` -> `/auth/email/verify` 200 `{email_verified,
  true}` -> replay 422 `errors[0].field === 'token'`. The E2E account created
  during that check was removed in the cleanup below.

### 21.7 UI retheme (semantic tokens, pink/light-blue)

- `frontend/src/index.css` rethemed through the existing semantic-token
  system: light theme uses white canvas with the mandated rose `#F8BBD0`
  (`--t-deep` bands) and `#B3E5FC` (`--t-tint` chips/selection) plus a
  contrast-safe deep-rose accent `#C2185B`; the dark theme is rose-black with
  `#F8BBD0` as the accent and `#B3E5FC`-derived selection. No hex literals
  exist in components (theme-color meta updated). Plaintext pre-change
  snapshot: `backups/ui-2026-10-19_0827/` (rollback = restore
  `frontend/src/index.css`).

### 21.8 Dev-database cleanup (destructive, exact ids)

- Removed conclusively-identified test rows from `consultation_platform`:
  users **23, 24, 25** (`smoke-client-*@example.com`, 2026-10-19 smoke),
  user **26** (`invite-e2e@example.com` + consultant profile #7 cascade),
  user **27** (`verify-e2e-2026@example.com`), appointment **52**, review
  **2**, report **1**. Final counts: users 21, appointments 50, reviews 0,
  reports 0, profiles 6, email tokens 0. Uncertain rows (e.g. user 22,
  appointment 51, legacy mojibake profile headlines) were left untouched.

### 21.9 Verification record (2026-10-19)

- Backend `php artisan test` with `RUN_MARIADB_CONCURRENCY=1`: **221 passed,
  965 assertions, 0 skipped**; `pint` clean.
- Frontend: `tsc -b`, `npm run lint`, `npm run build` all clean.
- Browser: 33 screenshots (fa/en x light/dark x desktop/tablet/mobile) on the
  dev stack; new surface (admin EN editors, pricing USD badges, verification
  banner) confirmed; no horizontal overflow regressions observed.
- Real SMTP delivery is NOT configured (log driver only); the password-setup
  link flow requires an operator to hand the URL over; no payment provider
  integrated; single ~750 kB chunk warning pre-exists (deferred chunk-split).

## 22. Shared-hosting deployment package (2026-10-19)

Built. Package: `deploy/sora-htdocs-20261019-122307/` — contents upload
directly into the user's existing `htdocs` (no nested htdocs; original
project untouched; full backup verified at
`backups/deploy-20261019-122307/`).

Layout: SPA+statics at htdocs root (`index.php` front controller,
`.htaccess`, `build/**` with `base=/build/`, real `storage/` public disk);
Laravel app in `sora/` (`app`, `bootstrap`, `config`, `database/migrations`,
`lang`, `resources`, `routes`, `storage`, `vendor@no-dev`, `.env`)
with deny-all `.htaccess`. Deployment-copy adaptations: `bootstrap/app.php`
public path → htdocs + `trustProxies`; `config/filesystems.php` public disk
root → `base_path('../storage')`, links emptied; `routes/web.php` serves the
SPA shell for `/` + deep links (catch-all excludes `/api/*`, `/up`, the
configured ADMIN_PATH); `config/logging.php` adds a `mail` channel so
`MAIL_MAILER=log` writes `storage/logs/mail.log` instead of being silently
discarded; production `.env` with fresh APP_KEY + randomized ADMIN_PATH.

Verification highlights (isolated XAMPP Apache 2.4.58 instance, port 8091,
`AllowOverride All`, PHP 8.2, MariaDB scratch DB `sora_deploy_test`, dropped
afterwards): 30/30 checks green after fixes — SPA shell + deep links,
API health, build assets, all security denials (`.env`, `/sora/**`, dotfiles,
`composer.*`, `*.md`), directory listing off, private admin shell (noindex +
no-store), register/login/me, admin create/login + unauthorized 401,
email-verification E2E with single-use replay 422, schema.sql round-trip
import (22 tables, no data), zero localhost URLs / demo creds / dev admin
path in app code and bundle. Known-unsupported: nginx (ignores .htaccess),
no on-host CLI/on-host Composer needed; host-side re-check list in the
package `DEPLOY_README.md`. Rollback = don't upload (original project
byte-identical to backup; XAMPP Apache config restored after temporary
testing).

## 23. Railway (container) deployment: single-service Architecture Decision (2026-10-19)

Goal: make the repository deploy as ONE Railway web service (Laravel API +
React SPA + private admin shell on a single origin) with reproducible builds
and no fragile detection. The shared-hosting/cPanel deployment (A22) is
preserved exactly: all additions are new files at the repository root
(Dockerfile, .dockerignore, docker/), documented in `RAILWAY_DEPLOY.md`.

Decision (single service, Dockerfile-based):
- **Container**: `php:8.2-apache` + `pdo_mysql`, `mbstring`, `opcache`;
  docroot = `backend/public`, application sources stay OUTSIDE the docroot
  (no `.env`, `artisan`, config, or storage exposure). SHA-pinned extensions:
  pdo_mysql (Railway MySQL) and mbstring with libonig; no `ext-gd` is
  required (verified: no composer package requires it).
- **Frontend build inside the image** (multi-stage node:22-alpine):
  `npm ci && npm run build -- --base=/build/` emitting
  `backend/public/build` — the exact artifact contract validated for cPanel
  (shell document references `/build/assets/*`, Vite manifest at
  `backend/public/build/.vite/manifest.json`).
- **Routing** (now in the repository, previously deploy-package-only):
  `routes/web.php` serves `/` and a single catch-all `/{panel}` GET route.
  The catch-all serves the private admin shell when the path equals the
  runtime `ADMIN_PATH` exactly (`hash_equals` inside AdminPanelController,
  which remains the authoritative check) and the public SPA shell for every
  other GET path — unknown paths therefore receive the SAME public SPA
  response, so probing the private path reveals nothing (improvement over
  the boot-time-excluded regex: no environment-order dependency in route
  compilation, and no 404 leakage shape). `route:cache` is NOT used (the
  closure is not serializable); `config:cache` runs at container start so
  config reflects the real runtime env.
- **Migrations**: never automatic. Controlled procedure = set the service
  variable `RUN_MIGRATIONS=true` for one deploy/validation, then remove it
  (docker/entrypoint.sh honors it). Railpack's auto `migrate --force` on
  every start was rejected: a DB outage at container boot would crash-loop
  and multiple replicas could race on `migrations`.
- **Proxy/HTTPS**: `$middleware->trustProxies(at: '*')` added to the repo
  `bootstrap/app.php` (identical to the already-deployed cPanel package
  version — no behavior change on cPanel). Session cookies: set
  `SESSION_SECURE_COOKIE=true` in the Railway environment.
- **Database**: Railway MySQL 8.x template. All 22 migrations reviewed:
  standard Laravel schema API + one portable backfill (`DB::raw` correlated
  UPDATE) + enum/foreignId/unique-string columns — MySQL 8-compatible;
  `utf8mb4_unicode_ci` OK. No automatic data migration: schema is created
  empty by the controlled migration run; existing MariaDB data, if ever
  migrated, requires an explicit operator-approved dump/import.
- **Initial admin**: `railway ssh` (supported by the Railway CLI) then
  `php artisan admin:create --name= --email= --password=` (refuses if any
  admin exists; no credentials committed anywhere).
- **Health**: `/up` (framework) and `/api/v1/health`. Note: `/api/v1/health`
  always returns HTTP 200 (records `database: unavailable` instead of
  failing) — use `/up` as the Railway healthcheck path.
- **cPanel compatibility**: repo route/bootstrap changes are byte-equivalent
  in intent to the deployed package's adapted copies; new root files never
  enter the cPanel package (which contains only htdocs + sora/); standard
  `npm run build` behavior unchanged; sample.html untouched.

## 24. Production HTTP 500 audit: database reachability and truthful readiness (2026-10-19)

Reported symptom (Railway, host `sora-production-4adf.up.railway.app`):
`GET /svg` -> HTTP 500 (509 ms upstream). Investigation of the live service
(GET only) plus a local reproduction established the causal chain:

| Request | Production | Local, DB up | Local, DB down |
|---|---|---|---|
| `/` (SPA shell) | 500 | 200 | 500 |
| `/svg`, `/consultants` | 500 | 200 | 500 |
| `/api/v1/consultants` | 500 | 200 | 500 |
| `/api/v1/health` | 200 `database: unavailable` | 200 `database: ok` | 200 `database: unavailable` (now 503) |
| `/up` | 200 | 200 | 200 |
| `/favicon.ico`, `/build/*` | 200 | 200 | 200 |

Root cause: the application cannot reach its database in production. The
session store uses the `database` driver (config/session.php) and the API
queries the database, so every request that touches the database fails with
HTTP 500, while database-independent requests (liveness, static assets) stay
200. `/svg` is not a special route - it is simply an unknown path that
reaches the SPA shell route, which then fails in the session layer. The SPA
build itself is present and intact in the image (`/build/index.html` and the
hashed asset both return 200), so "missing assets" was ruled out.

Defect 1 (diagnosability): the container started without validating its
configuration, so a missing or mis-linked database produced a silent stream
of 500s. `docker/entrypoint.sh` now validates the required variables
(APP_KEY, DB_*) - printing variable NAMES only, never values - and probes
the database with `php artisan db:show`; failures abort the startup (missing
configuration) or are logged with an actionable hint (unreachable database).

Defect 2 (truthful readiness): `/api/v1/health` answered HTTP 200 even when
the database was unreachable, so the platform and the SPA could not
distinguish "process up" from "application usable". The endpoint now
answers 503 with `status: unhealthy` when the database is down; the JSON
body shape is unchanged, so existing consumers keep working. `/up` remains
the liveness probe (a database outage must not turn into a restart loop).

Defect 3 (reported path): SPA routing for unknown paths (`/svg`, deep
links) is now locked by regression tests (SpaShellRoutingTest) so the
reported path can never silently regress.

Production data: untouched. The Railway-side cure is operational (link the
MySQL service, set DB_* references, run the migrations once) and is
documented in RAILWAY_DEPLOY.md section 4.1.
