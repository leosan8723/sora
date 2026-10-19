\# Implementation Plan



\## Project: Consultation Booking and Session Management Platform



\## 1. Objective



Build a production-oriented web application with a Persian RTL interface, a Laravel backend, a React and TypeScript frontend, and MariaDB as the primary database (see `ARCHITECTURE.md` for the database decision and rationale).



Implementation must proceed incrementally, with verification at every phase.



\## 2. Current Status



\- \[x] Phase 0: Workspace and environment audit (completed 2026-10-16).

\- \[x] Phase 1: Architecture and technical decisions (completed 2026-10-16; see `ARCHITECTURE.md`).

\- \[x] Phase 2: Project foundation and development tooling (completed 2026-10-16).

\- \[x] Phase 3: Database schema and core domain models (completed 2026-10-16).

\- \[x] Phase 4: Authentication and authorization (completed 2026-10-16).

\- \[x] Phase 5: Consultant profiles and discovery (completed 2026-10-16).

\- \[x] Phase 6: Availability and appointment booking (completed 2026-10-16).

\- \[x] Phase 7: Client and consultant dashboards (completed 2026-10-16).

\- \[ ] Phase 8: Administration dashboard.

\- \[ ] Phase 9: Notifications and appointment lifecycle.

\- \[ ] Phase 10: Security hardening and automated testing.

\- \[ ] Phase 11: UI polish, accessibility, and responsive testing.

\- \[ ] Phase 12: Deployment preparation and documentation.



Do not mark a phase complete until its acceptance criteria have been verified.



\## 3. Phase 0: Workspace and Environment Audit



\### Objectives



Understand the existing workspace before creating or modifying application files.



\### Tasks



\- Inspect the workspace structure and existing files.

\- Read `DESIGN.md` and `AGENTS.md`.

\- Identify the existing project state.

\- Check PHP, Composer, Node.js, npm, and MariaDB availability and versions.

\- Inspect Laravel, React, and TypeScript tooling if already present.

\- Determine whether the database server (MariaDB) is running and accessible.

\- Inspect XAMPP availability without changing its configuration.

\- Identify existing Git repositories and uncommitted changes.

\- Identify reference projects and keep them separate from the target application.



\### Acceptance Criteria



\- A factual environment report exists.

\- The current project structure is understood.

\- MariaDB availability is confirmed or a blocker is documented.

\- No existing files, services, or databases have been changed unnecessarily.

\- A proposed technical architecture is documented.



Do not begin destructive operations during this phase.



\## 4. Phase 1: Architecture and Technical Decisions



\### Objectives



Define the architecture before implementation.



\### Tasks



\- Choose the Laravel project structure.

\- Choose the React and TypeScript build setup.

\- Define the frontend/backend integration approach.

\- Define authentication and session management.

\- Define API conventions and error responses.

\- Define the MariaDB schema strategy.

\- Define role and permission boundaries.

\- Define the timezone and appointment scheduling policy.

\- Define the testing strategy.

\- Define environment configuration and secret management.

\- Record significant decisions and their reasoning.



\### Acceptance Criteria



\- The architecture is documented.

\- Frontend and backend responsibilities are clear.

\- API boundaries are defined.

\- Authentication and authorization approaches are specified.

\- The database approach is compatible with MariaDB.

\- No major architectural choice is silently substituted.



\### Decision Record (2026-10-16)

\- Database: MariaDB 10.4.32 (XAMPP) chosen over PostgreSQL and MySQL. Rationale: already running, `pdo_mysql` enabled, no installs or service restarts, Laravel 12 supported, InnoDB provides the transactions and constraints required for conflict-free booking. PostgreSQL is not installed and would require an installer, a new service, a `php.ini` change, and an Apache restart.

\- Backend: Laravel 12.x on PHP 8.2.12 (Laravel 13 requires PHP >= 8.3).

\- Frontend: React 19 + TypeScript + Vite, Tailwind CSS with logical properties for RTL, Vazirmatn typography.

\- Full decision text, API conventions, booking-concurrency design, timezone policy, and test strategy: see `ARCHITECTURE.md`.



\## 5. Phase 2: Project Foundation



\### Tasks



\- Initialize or organize the Laravel backend.

\- Initialize or organize the React and TypeScript frontend.

\- Configure environment variables and example configuration files.

\- Configure code quality and testing tools.

\- Establish a consistent UI foundation.

\- Establish API communication and error handling.

\- Add setup documentation.

\- Verify the frontend build and backend baseline.



\### Acceptance Criteria



\- Backend and frontend start using documented commands.

\- Environment-specific secrets are not committed.

\- Baseline checks pass.

\- The application has a clear and maintainable structure.



### Completion Record (2026-10-16)

- Backend: Laravel 12.70.0 scaffolded at `backend/`. Laravel Sanctum installed via `install:api`; `routes/api.php` published under the `api/v1` prefix; `personal_access_tokens` migration applied. `app/Models/User.php` now uses `HasApiTokens`. Health endpoint `GET /api/v1/health` returns application, environment, timezone, and database status.
- API conventions: JSON errors for `api/*`; validation errors are shaped to `{ message, errors: [{ code, field, message }] }` in `bootstrap/app.php`.
- CORS: `config/cors.php` restricts `api/*` to `FRONTEND_URL` (default `http://127.0.0.1:5173`). Verified end-to-end, `Access-Control-Allow-Origin` is echoed for the allowed origin only.
- Timezone: `config/app.php` now reads `APP_TIMEZONE` from the environment (previously hardcoded to `UTC`). The Phase 1 wording paired `Asia/Tehran` with UTC persistence, which cannot both hold. User-approved resolution: **store UTC, convert for display** — `APP_TIMEZONE=UTC`, presentation converts to `Asia/Tehran` (see `ARCHITECTURE.md` §10.1). Verified via the health endpoint: `time_utc` `+00:00`, `time_tehran` `+03:30`, `display_timezone` `Asia/Tehran`.
- Frontend: React 19 + TypeScript + Vite 8 at `frontend/`. Runtime dependencies: `react-router`, `@tanstack/react-query`, `react-hook-form`, `zod`, `@hookform/resolvers`, `motion`, `@fontsource-variable/vazirmatn`. Dev: `tailwindcss` + `@tailwindcss/vite` (v4) and `oxlint`.
- UI foundation: dark tokens from `DESIGN.md` mapped to a Tailwind v4 `@theme`; `html` set to `lang="fa" dir="rtl"`; Persian typography via Vazirmatn; Jalali and Persian-number formatting helper in `src/lib/format.ts`; typed API client in `src/lib/api.ts` with a normalized `ApiError`.
- Checks executed: `npm run build` (passed, 554 modules), `npm run lint` (oxlint, exit 0), `php artisan test` (2 passed), `vendor/bin/pint --test` (passed after formatting), live `GET /api/v1/health` over HTTP (200, database `ok`), CORS verification (passed).
- Repository: Git initialized at the workspace root; `.env` files, `vendor/`, and `node_modules/` are ignored; no commit was created.
- Known items: the default MariaDB `test` database remains visible to the `consultation_app` user (not revoked). The timezone conflict described above is resolved and requires no further Phase 3 decision.



\## 6. Phase 3: Database Schema and Domain Models



\### Core Entities



Evaluate and implement appropriate models for:



\- Users.

\- Roles and permissions.

\- Consultant profiles.

\- Specialties.

\- Consultation types.

\- Consultant availability.

\- Appointment slots or scheduling rules.

\- Appointments.

\- Notifications.

\- Audit events, where appropriate.



Do not introduce unnecessary entities without a clear business reason.



\### Tasks



\- Design relationships and constraints.

\- Implement Laravel migrations.

\- Add model validation and relationships.

\- Add development seeders with fictional data.

\- Define appointment statuses and allowed transitions.

\- Add indexes for common queries.

\- Use transactions and appropriate database constraints for booking consistency.



\### Acceptance Criteria



\- Migrations work on MariaDB.

\- Relationships and constraints are tested.

\- Seeders do not use real personal information.

\- Concurrent booking protection is addressed in the schema and application design.



### Completion Record (2026-10-16)

- Migrations (`2026_10_16_160000`–`160700`): `users` role/profile fields, `specialties`, `consultant_profiles`, `consultant_specialty`, `consultation_types`, `availability_rules`, `appointments`, `notifications`. Applied to MariaDB; `migrate:rollback` and `migrate:fresh --seed` both verified.
- Booking concurrency: `appointments.active_slot_key` (`{consultant_id}|{starts_at UTC}`) is unique and derived in an `Appointment` `saving` hook for active statuses only; terminal rows store `NULL` (multiple `NULL`s allowed by the unique index). Appointment FKs are `RESTRICT` (history preserved); `cancelled_by_id` is `SET NULL`.
- Enums: `App\Enums\UserRole`; `App\Enums\AppointmentStatus` with `isActive()` and `canTransitionTo()` — `pending → {confirmed, cancelled}`, `confirmed → {completed, cancelled}`, terminal states final. Transition enforcement is deferred to the Phase 6/9 services.
- Models: `Specialty`, `ConsultantProfile` (scopes `active`/`discoverable`), `ConsultationType`, `AvailabilityRule`, `Appointment`; `User` extended with the `role` cast, relations, and `isAdmin`/`isConsultant`/`isClient`.
- Factories and seeders: five new factories; `UserFactory` role/inactive states; `SpecialtySeeder` (idempotent) and `DemoDataSeeder` (1 admin, 6 consultants, 12 clients, 48 appointments — fictional Persian data, `example.com/net/org` emails, `password` dev credentials).
- Test database fix: `phpunit.xml` had the DB env commented out, which would have pointed `RefreshDatabase` at the development MariaDB database; switched to in-memory SQLite. MariaDB-specific concurrency tests need a dedicated test database (see `ARCHITECTURE.md` §14.1) — pending approval for Phase 6/10.
- Checks executed: `php artisan test` (24 passed, 115 assertions), `vendor/bin/pint --test` (passed), migrations applied/rolled back/fresh on MariaDB (passed), `npm run build` and `npm run lint` (passed, unchanged).
- Next phase: Phase 4 — authentication and authorization (Sanctum registration/login/logout, role authorization, rate limiting, access-control tests).



\## 7. Phase 4: Authentication and Authorization



\### Tasks



\- Implement registration, login, logout, and account management.

\- Implement secure password handling.

\- Enforce role-based authorization.

\- Protect API endpoints.

\- Validate incoming requests.

\- Apply suitable rate limits.

\- Test unauthorized access and cross-user data access.



\### Acceptance Criteria



\- Users can access only permitted operations.

\- Authorization is enforced server-side.

\- Sensitive account operations are tested.

\- Authentication failures are handled safely.



### Completion Record (2026-10-16)

- Endpoints (`/api/v1/auth`): `POST /register`, `POST /login`, `GET /me`, `POST /logout`, `PUT /profile`, `PUT /password`; protected routes use `auth:sanctum`.
- Registration creates only `client` accounts; `role`/`is_active` are never accepted from the payload (validated fields only).
- Passwords: bcrypt via the `hashed` cast; policy `Password::min(8)->letters()->numbers()`; changing a password requires the current one and revokes all other tokens.
- Authorization: gates `access-admin`/`access-consultant` and a `role` middleware (`EnsureUserHasRole`); resource policies follow in later phases.
- Rate limits: login 5/min per email+IP and 20/min per IP, register 10/min per IP, password 5/min per user+IP.
- Failure handling: identical generic 422 for unknown account and wrong password (no enumeration); suspended accounts get 403 only after password verification.
- Bug fixed: unauthenticated `api/*` requests that omit `Accept: application/json` returned HTTP 500 ("Route [login] not defined") instead of 401 JSON.
- Localization: `lang/en` published and `lang/fa` added so API errors are Persian.
- Tests: `RegistrationTest`, `LoginTest`, `AuthorizationTest`, `PasswordChangeTest` (+ `AssertsApiValidation` trait). `Tests\TestCase` clears auth guards between requests to mirror real per-request behaviour.
- Checks executed: `php artisan test` (47 passed, 240 assertions), `vendor/bin/pint --test` (passed), live auth flow (register/login/me/logout → 401 after logout), 429 rate limit, Persian messages, CORS preflight, no leftover processes, dev DB token table emptied.
- Next phase: Phase 5 — consultant profiles and discovery (public listing, filters, pagination, detail endpoint).



\## 8. Phase 5: Consultant Profiles and Discovery



\### Tasks



\- Implement consultant profile management.

\- Implement specialties and consultation types.

\- Implement public consultant listing and detail pages.

\- Add filtering and search.

\- Display consultation fees, durations, and availability where appropriate.

\- Build responsive Persian RTL interfaces.



\### Acceptance Criteria



\- Consultant data persists correctly.

\- Public information is separated from private information.

\- Filters and search return correct results.

\- Empty, loading, and error states are implemented.



### Completion Record (2026-10-16)

- Public discovery API: `GET /api/v1/specialties`, `GET /api/v1/consultants` (filters `q`, `specialty` slug, `sort=name|experience|newest`, `per_page` 1–50) and `GET /api/v1/consultants/{consultant}` which returns 404 unless the consultant is active **and** verified.
- Owner self-management API (`auth:sanctum` + `role:consultant`, prefix `/api/v1/consultant`): `GET|PUT /profile`, `PUT /specialties` (sync), `GET|POST /consultation-types`, `PUT|DELETE /consultation-types/{id}`, `GET /availability` (read-only; rule CRUD is Phase 6).
- Resources separate public from private data: `SpecialtyResource`, `ConsultationTypeResource`, `AvailabilityRuleResource`, `ConsultantSummaryResource`, `ConsultantDetailResource` (public); `ConsultantProfileResource` and `ConsultantConsultationTypeResource` (owner, adds `is_active`/`sort_order`). No user email, phone, id, or `user_id` is exposed publicly.
- Consultation types are owner-scoped via `ConsultationTypePolicy` and `Gate::authorize`; another consultant's type resolves to 404.
- Validation via `ConsultantIndexRequest`, `UpdateConsultantProfileRequest`, `SyncSpecialtiesRequest`, `StoreConsultationTypeRequest`, `UpdateConsultationTypeRequest`.
- First-write provisioning: `GET /consultant/profile` returns 404 until the consultant has a profile; `PUT /consultant/profile` provisions it with `firstOrNew`/`firstOrCreate` and always answers 200 (a resource wrapping a just-created model would otherwise answer 201).
- Frontend (React/TypeScript, Persian RTL): public pages `/consultants` (URL-driven filters, debounced search, skeletons, empty/error states, pagination) and `/consultants/:id` (header, bio, consultation types with fees/durations, weekly availability, booking CTA disabled until Phase 6); auth-aware shell/nav and `/login`; consultant dashboard `/dashboard` for profile, specialties and consultation-type management.
- UI primitives added: `Button`, `Badge`, `Spinner`, `StateMessage`, `Skeleton`, `Pagination`, `Field` (`TextField`/`TextAreaField`/`SelectField`); shared client utilities `api.ts`, `authToken.ts`, `format.ts`, `formErrors.ts`, `useDebouncedValue.ts`.
- Backend tests: `ConsultantDiscoveryTest`, `ConsultantProfileManagementTest`, `ConsultationTypeManagementTest`.
- Checks executed: `php artisan test` (67 passed, 335 assertions), `vendor/bin/pint --test` (passed), `npm run build` (passed), `npm run lint` (passed), and a live smoke test of the discovery + owner endpoints (22 checks; the two profile-write checks were re-run in isolation and passed — the initial "failures" were a leading-space artifact in the smoke script trimmed by Laravel's `TrimStrings`, not a defect). Dev DB restored to its pre-check state; no leftover tokens or records.
- Next phase: Phase 6 — availability and appointment booking (bookable slot generation, atomic booking with conflict protection, cancellation/rescheduling services).



\## 9. Phase 6: Availability and Appointment Booking



\### Tasks



\- Implement consultant availability management.

\- Generate or retrieve bookable time slots.

\- Implement appointment creation.

\- Prevent overlapping and duplicate bookings.

\- Handle concurrent booking attempts safely.

\- Implement cancellation and rescheduling rules.

\- Enforce booking permissions and status transitions.

\- Define timezone conversion and storage rules.



\### Acceptance Criteria



\- Valid appointments can be booked.

\- Unavailable slots cannot be booked.

\- Concurrent requests cannot create conflicting appointments.

\- Cancellation and rescheduling obey documented policies.

\- Critical scheduling behavior is covered by automated tests.



\### Completion Record (2026-10-16)

- Config: `backend/config/booking.php` (`timezone=Asia/Tehran`, `max_range_days=31`, `default_range_days=7`, `minimum_lead_minutes=5`, `cancellation_lead_hours=2`, `reschedule_lead_hours=2`).
- Availability rule CRUD (`auth:sanctum` + `role:consultant`, prefix `/api/v1/consultant`): `POST /availability` (201), `PUT|DELETE /availability/{availabilityRule}`, plus `GET /availability` from Phase 5. Owner-only `ConsultantAvailabilityRuleResource` (exposes `id`, `is_active`) and `AvailabilityRulePolicy`. Validation via `Store|UpdateAvailabilityRuleRequest` (weekday 0–6, `H:i`, end > start, no active overlap on the same weekday).
- Slot generation `App\Services\Booking\SlotGenerator`: candidate starts step by the consultation-type duration from the rule `start_time` and must fit within `end_time`; active (`pending`/`confirmed`) appointments block overlapping starts; `minimum_lead_minutes` filters near-term slots; Tehran ⇄ UTC conversions via Carbon (Iran is UTC+03:30 year-round → 09:00 Tehran = 05:30Z). Public `GET /api/v1/consultants/{consultant}/slots?consultation_type=&from=&to=` grouped by day in a `data`+`meta` envelope (`ConsultantSlotsRequest`, range capped at 31 days → 422).
- Booking services `App\Services\Booking\BookingService`: `book`/`reschedule`/`cancel` run inside `DB::transaction(..., 5)`; lock the consultant profile (and appointment) with `SELECT ... FOR UPDATE`; validate the requested time against availability (`422` when off-grid/past/inactive, `409` when the active slot is already held). Duplicate-key violations (`SQLSTATE 23000` / driver `1062`) are raised as `App\Exceptions\BookingConflictException` → 409 with a `booking_conflict` error code.
- Appointments API (`auth:sanctum`): `POST /appointments` (`role:client`, `throttle:booking` 20/min), `GET /appointments/{appointment}`, `POST /appointments/{appointment}/cancel`, `POST /appointments/{appointment}/reschedule` (`role:client`). `AppointmentResource` returns UTC ISO instants plus Tehran display strings and a `can.cancel`/`can.reschedule` block from `AppointmentPolicy`. Non-owners resolve to 404 rather than 403 (no IDOR).
- Frontend (React/TypeScript, Persian RTL): `features/booking` (types/api/hooks), `BookingPanel` (consultation-type + day + slot picker with optional notes; used for both booking and rescheduling), `AppointmentDetailPage` at `/appointments/:id` (status, details, cancel with reason, reschedule), `RequireAuth` route guard, and the booking CTA on `ConsultantDetailPage` (guests redirected to `/login` with a return path; non-client roles see a notice). The read-only dashboard list was replaced by `AvailabilityEditor` (create/edit/delete per weekday with active toggle).
- Backend tests: `AvailabilityManagementTest` (7), `SlotGenerationTest` (10), `AppointmentBookingTest` (10), `AppointmentLifecycleTest` (11).
- Checks executed: `php artisan test` (105 passed, 482 assertions), `vendor/bin/pint --test` (passed after fixing two files), `npm run build` (passed; single ~605 kB entry chunk, code-splitting deferred to Phase 11), `npm run lint` (passed; one pre-existing `set-state-in-effect` warning in `AuthProvider`), and a live API smoke test of the Phase 6 endpoints (18 checks: register, discover consultant, fetch slots, book 201, duplicate 409 `booking_conflict`, owner view, guest 401, slot removed from public list, cancel, slot freed, off-grid 422, unknown consultant 404). The seeded MariaDB database was restored (19 users, 48 appointments, 0 smoke rows; the server stopped).
- Deferred (now resolved): a dedicated MariaDB **test** database for genuinely parallel concurrency tests was created after explicit approval — see the addendum below and Section 14.1 of `ARCHITECTURE.md`.
- Next phase: Phase 7 — client and consultant dashboards (appointment listings, consultation scheduling/confirmation flow, role dashboards).

\#### Concurrency Addendum (2026-10-16)

- Created the dedicated test database `consultation_platform_test` (utf8mb4 / utf8mb4_unicode_ci) and granted `ALL PRIVILEGES` on it to the existing least-privilege `consultation_app` user. The development database (`consultation_platform`) and every other database were untouched; no service was restarted.
- Added a real, cross-process parallel-booking test against MariaDB:
  - `backend/tests/Feature/Booking/RealDatabaseBookingConcurrencyTest.php` — opt-in (`RUN_MARIADB_CONCURRENCY=1`) and self-guarding (skips without the flag or if the test database is unreachable; hard-asserts the connected database is the test database and the driver is MariaDB/MySQL). Uses no `RefreshDatabase` so worker processes can see committed fixtures; runs `migrate:fresh` on the dedicated test database and deletes its rows in `tearDown`.
  - `backend/tests/Support/booking_concurrency_worker.php` — a plain script (not collected by PHPUnit) that boots the application and dispatches one authenticated booking request through the HTTP kernel behind a shared time barrier.
- Checks executed: `RUN_MARIADB_CONCURRENCY=1 php artisan test --filter=RealDatabaseBookingConcurrencyTest` (3 passed, 26 assertions — two simultaneous cross-process requests on one slot yield exactly one `201` and one `409` `booking_conflict`; plus unique-index backstop and cancelled-slot cases), full `php artisan test` (105 passed, 3 skipped, 482 assertions) and `vendor/bin/pint --test` (passed).
- The development database was verified unchanged (19 users, 48 appointments) and the dedicated test database was left with zero test rows.
- Behaviour beyond the database creation is documented in `ARCHITECTURE.md` §14.1 (invocation, guard rails, `migrate:fresh` scope limited to the test database).




\## 10. Phase 7: Client and Consultant Dashboards



\### Client Features



\- Upcoming appointments.

\- Appointment history.

\- Appointment details and status.

\- Permitted cancellation and rescheduling.

\- Profile management.



\### Consultant Features



\- Upcoming appointments.

\- Appointment history.

\- Availability management.

\- Profile management.

\- Authorized appointment status updates.



\### Acceptance Criteria



\- Dashboard data is role-specific.

\- Users cannot access another user's private data.

\- All critical actions are connected to the backend.



### Completion Record (2026-10-16)

- Backend listing API: `GET /api/v1/appointments` (`AppointmentIndexRequest`;
  `scope=upcoming|past|all`, optional `status`, `per_page` 1–50, `page`) with
  role-aware `scopeForParticipant` scoping (client own bookings, consultant
  profile bookings, admin everything) — no separate role middleware needed.
  `upcoming` = active statuses at/after now ascending; `past` = terminal or
  started-before-now descending; `all` = `starts_at` descending.
- Backend status API: `POST /appointments/{appointment}/status`
  (`UpdateAppointmentStatusRequest`: `confirmed` or `completed` only) guarded by
  `AppointmentPolicy::updateStatus` (consultant owner or admin, active only);
  `BookingService::transitionStatus` applies the state machine inside a
  transaction and turns illegal transitions into 422 Persian `status` messages.
- `AppointmentResource::can` extended with `confirm` (pending + policy) and
  `complete` (confirmed + policy); `Appointment` gained
  `forParticipant`/`upcoming`/`past` scopes.
- Backend tests: `AppointmentListingTest` (11 tests / 52 assertions),
  `AppointmentStatusTransitionTest` (10 / 33). Harness notes for future tests:
  re-authenticate before follow-up requests (the test `call()` forgets guards
  after each request → 401), and a 09:00–12:00 / 30-min rule yields exactly
  6 candidate slots (indexes 0–5).
- Frontend (Persian, RTL): `features/booking` list/status API + `useAppointments`
  (`keepPreviousData`) + `useUpdateAppointmentStatus` (cache update + list
  invalidation; write mutations also invalidate lists now) and a shared
  `AppointmentCard`; `pages/AppointmentsPage.tsx` at `/appointments` with
  URL-driven scope tabs and pagination; `features/profile`
  (`ProfileForm` PUT `/auth/profile`, `PasswordForm` PUT `/auth/password`,
  matching the register zod rules) and `pages/ProfilePage.tsx` at `/profile`;
  `features/consultant-dashboard/AppointmentsManager.tsx` on `/dashboard` with
  upcoming/past tabs and inline two-step confirm/complete actions;
  `AppShell` nav gains `نوبت‌های من` (clients) and `پروفایل` (authenticated
  users). `/appointments` and `/profile` are `RequireAuth`-protected.
- Checks executed: `php artisan test` (**126 passed, 3 skipped, 567
  assertions**; 129 with the opt-in concurrency flag), `pint --test` passed,
  frontend `npm run build` passed and `npm run lint` passed (the same
  pre-existing `set-state-in-effect` warning in `AuthProvider`; single
  ~620 kB entry chunk, code-splitting still deferred to Phase 11).
- Next phase: Phase 8 — administration dashboard (user/role management,
  consultant oversight, appointment monitoring, operational summaries,
  `audit_events`).

\#### Visual Foundation Addendum (2026-10-17)

- User-directed interlude (no API/business-logic changes): established the shared
  visual foundation for the whole site, adapted from `sample.html`.
  - `index.css` redesign-token pass: `deep` band colour (`#0a0a0a`), layered
    surfaces (`surface-soft`/`hairline-soft`/`accent-wash`), `danger` tone,
    radius ramp (control 8 / panel 12 / card 20 / hero 28), shadow tokens,
    display type scale (clamp-based, Vazirmatn weight contrast), photo-fade
    mask utilities (`fade-bottom`/`fade-top`), `glass` chrome, shimmer keyframe,
    and a global `prefers-reduced-motion` guard.
  - New shared layout components: `Logo` (SVG mark + wordmark, brand favicon),
    `Container` (1200px page / narrow), `Section` (band + spacing), `Reveal`
    (scroll-entrance, reduced-motion aware), `Header` (fixed 64px glass bar,
    role-aware links, mobile drawer with Escape/scroll-lock), `Footer`
    (brand, nav columns matching real routes, copyright in 1405, no invented
    contact details).
  - `AppShell` now composes Header + Container + Footer; every route inherits
    consistent width/spacing without touching page logic.
  - `HomePage` rebuilt on the foundation: full-bleed hero photo with the
    reference's gradient-fade treatment, orchestrated entrance, a factual
    service-status strip, a three-step sequence (real user flow), and a calm
    image band. Local assets only: `src/assets/{hero,calm,rest,still}.jpg`
    downloaded from the reference (low-res template strips were discarded).
  - Primitives polished without API changes: `Button` (shadow/press/lg size),
    `Badge` (distinct `success` tone), `Field` (focus/hairline hierarchy),
    `Skeleton` (shimmer), `StateMessage` (dashed calm card).
  - Checks executed: `npm run build` passed, `npm run lint` passed (same
    single pre-existing `AuthProvider` warning), Vite dev-server module
    transforms verified (HTTP 200) for all touched files. Page-level redesigns
    of the remaining routes are deliberately deferred for the owner's review.




\## 11. Phase 8: Administration Dashboard



\### Tasks



\- Implement administrative authentication and access restrictions.

\- Manage users and roles.

\- Manage consultant profiles and specialties.

\- Monitor and manage appointments.

\- Provide appropriate operational summaries.

\- Record important administrative actions where necessary.



\### Acceptance Criteria



\- Administrative endpoints are protected.

\- Administrative actions are validated and auditable where appropriate.

\- Ordinary users cannot access administrative operations.



\## 12. Phase 9: Notifications and Appointment Lifecycle



\### Tasks



\- Define notification events.

\- Implement in-app notifications where appropriate.

\- Add booking, cancellation, and rescheduling notifications.

\- Add reminder support.

\- Configure email or SMS integrations only when credentials and provider choices are available.

\- Handle delivery failures without corrupting appointment records.



\### Acceptance Criteria



\- Notification events are generated correctly.

\- Delivery status is handled accurately.

\- External delivery is not reported as working until tested.



\## 13. Phase 10: Security and Automated Testing



\### Tasks



\- Review authentication and authorization.

\- Test booking concurrency.

\- Test input validation.

\- Review sensitive data exposure.

\- Test common web vulnerabilities.

\- Review database transactions and constraints.

\- Run backend and frontend test suites.

\- Fix critical and high-priority defects.



\### Acceptance Criteria



\- Critical business rules have automated tests.

\- Critical security issues found during testing are resolved.

\- Remaining known limitations are documented.



\## 14. Phase 11: UI Polish and Accessibility



\### Tasks



\- Review all pages for visual consistency.

\- Verify Persian typography and RTL behavior.

\- Test desktop, tablet, and mobile layouts.

\- Verify keyboard navigation and focus states.

\- Review contrast, labels, validation messages, and feedback.

\- Remove unnecessary placeholder content.

\- Verify all important user flows end to end.



\### Acceptance Criteria



\- Core pages are responsive and visually consistent.

\- Important controls are accessible.

\- No critical layout or interaction defects remain.



\## 15. Phase 12: Deployment Preparation



\### Tasks



\- Document production requirements.

\- Prepare environment configuration.

\- Document MariaDB provisioning and migration procedures.

\- Configure secure deployment practices.

\- Document backups, logging, and operational monitoring.

\- Document build and startup commands.

\- Perform a final release checklist.



\### Acceptance Criteria



\- Deployment instructions are reproducible.

\- Secrets are excluded from source control.

\- Database migration and backup procedures are documented.

\- Known limitations are clearly disclosed.



\## 16. Execution Rules



\- Complete Phase 0 first.

\- Do not implement all phases in one operation.

\- Work only on the current phase unless dependencies require a small, justified preparatory change.

\- Run checks after each meaningful change.

\- Record actual results, not assumptions.

\- Keep this document synchronized with implementation progress.

\- Stop and report blockers rather than inventing successful outcomes.

\- Ask for approval before destructive actions, external spending, deployment, or changes to the required technology stack.

## Website Completion Task — Phases 4–6 Records (2026-10-18)

User-approved 7-part "complete the website & improve quality" task executed as a
6-phase sub-plan; Phases 1–3 (homepage diagnostics removal, consultation mode,
public About/FAQ/Contact pages + contact inbox) were recorded earlier. The
records below close Phases 4–6.

### Phase 4 Completion Record — Admin panel and pricing management (2026-10-18)

- Backend: `site_settings` key/value table (`SiteSetting` model, JSON `value`
  cast) + `App\Support\ConsultationModes` (effective modes = `supported_modes`
  setting ∩ enum values, falling back to `config('booking.supported_modes')`).
  Admin API under `auth:sanctum` + `role:admin` at `/api/v1/admin`:
  `summary`, `users` (GET/POST/PUT), `consultants` (GET/PUT), `appointments`
  (GET), `consultation-types` (GET/PUT — the pricing/offering editor),
  `settings` (GET/PUT), `contact-messages` (GET/PUT). Every admin `FormRequest`
  re-checks `$user?->isAdmin()`, so the route middleware is not the only gate.
- Security fix found while testing: deactivated accounts kept working API
  tokens. New `EnsureUserIsActive` middleware is prepended to the `api` group
  (`bootstrap/app.php`) and also enforced inside `EnsureUserHasRole`; factory
  fixes (`is_active` + `role` defaults in `UserFactory`) removed a class of
  in-memory-attribute failures across the suite.
- New tests: `AdminPanelTest` (12) and `AdminPricingSettingsTest` (6) — pricing
  edits apply to new bookings only (existing rows keep their `price_toman` and
  `mode` snapshots), narrow `supported_modes` rejects new consultant offerings,
  non-admins get 403 on every admin route.
- Frontend: `features/admin/{types,api,useAdmin}.ts` and admin pages under
  `/admin` (`AdminLayoutPage` tab shell, summary, users, consultants,
  appointments, pricing, messages, settings), gated by
  `<RequireRole role="admin">`.

### Phase 5 Completion Record — Reviews and ratings, verified-only (2026-10-18)

- `consultant_reviews` migration: unique `appointment_id` (the data guarantee
  behind "verified-only"), `consultant_profile_id`, `client_id`, `rating` 1–5,
  `body`, `status` enum `pending|approved|rejected|hidden` (default `pending`),
  `moderated_at`. Applied to the dev MariaDB database and verified reversible
  (rollback + re-migrate clean).
- API: public `GET /consultants/{consultant}/reviews` (approved only, paginated,
  aggregate `avg_rating`/`reviews_count` inside `meta`; `?mine=1` additionally
  returns the caller's own unpublished reviews with `is_own`/`status`);
  `GET /consultants/{consultant}/reviewable-appointments`; `POST
  /consultants/{consultant}/reviews` (`throttle:reviews` = 5/hour + 10/day);
  `PUT|DELETE /reviews/{review}` (author only, edit returns the review to
  moderation); admin `GET|PUT|DELETE /admin/reviews` moderation queue.
- Eligibility is enforced in `StoreConsultantReviewRequest` (own completed
  appointment, matching consultant, no duplicate review) — the client can never
  submit what the server would not accept. Reviewer identity is reduced to a
  display name (given name + surname initial); ids, emails, and appointment
  details are never exposed. `ConsultantSummaryResource`/`ConsultantDetailResource`
  gained `avg_rating`/`reviews_count` from approved reviews only.
- Frontend: `features/reviews` (types/api/hooks, `StarRating`, `ReviewForm`,
  `ReviewsSection`) wired into `ConsultantDetailPage` (`#reviews` anchor), a
  review CTA on completed appointments, a rating chip on consultant cards, and
  the admin moderation page at `/admin/reviews`. No fabricated ratings anywhere:
  without approved reviews the UI states that plainly.
- New tests: `ConsultantReviewTest` (16) and `AdminReviewModerationTest` (7)
  covering eligibility, duplicate prevention, pending invisibility, ownership,
  edit-returns-to-moderation, deletion, aggregates, and the rate limit.

### Phase 6 Completion Record — Scoped visual pass (2026-10-18)

- Mobile RTL hero overflow fixed at the root: the hero used hard-coded negative
  inline margins (`-mx-6 sm:-mx-8`) that widened the page on small screens. It
  now uses a `.full-bleed` utility (`margin-inline: calc(50% - 50vw)`, which is
  direction-agnostic and avoids the `100vw` scrollbar trap), with `overflow-x:
  clip` on `body` as a safety net.
- Persian typography: heading `letter-spacing` reset from `-0.01em` to `normal`
  (the Latin tracking rule degrades Persian script).
- Scoped to the homepage and the new public/admin pages; no broad redesign and
  no changes to the booking flow.
- Checks executed: `php artisan test` — **178 passed, 3 skipped (770
  assertions)**; MariaDB concurrency suite (`RUN_MARIADB_CONCURRENCY=1`) — **3
  passed (26 assertions)**; `npm run build` — passed; `npm run lint` — exit 0
  with one pre-existing `set-state-in-effect` warning in `AuthProvider.tsx`.
  Live smoke on the dev server covered the admin API (summary/users/consultants/
  appointments/types/settings/messages) and the review endpoints.
- Known limitations: no email/notification delivery, no payment integration, no
  internationalisation beyond Persian, and the single ~700 kB entry chunk still
  awaits code-splitting (deferred to the deployment phase).

### Phase 7 Completion Record — Sora rebrand, bilingual fa/en, review reports (2026-10-19)

- Backend i18n: `SetRequestLocale` middleware (Accept-Language → app locale,
  fa default), all hardcoded Persian messages swept into
  `lang/{fa,en}/{booking,reviews,contact,admin}.php`, and `specialties.name_en`
  (migration + backfill + localized `SpecialtyResource`; DB data stays
  locale-independent, no duplication).
- Review reports completed end to end: `review_reports` migration/model,
  `POST /reviews/{review}/report` with dedicated `review-reports` throttle,
  eligibility only for approved non-own reviews and one-per-user, admin
  `GET /admin/review-reports` + resolve/dismiss endpoints (triage only; no
  emails), `AdminReportsPage` (`/admin/reports`) in the admin shell, and a
  report control on published reviews in `ReviewsSection` for signed-in users.
- Tests: `ApiLocalizationTest` (fa default, en via header, specialty names per
  locale, localized domain messages), `ReviewReportTest` (9 tests), and the
  ordering test in `ConsultantDiscoveryTest` pinned to `Accept-Language: fa`
  (Symfony test requests always carry an en-us header artifact).
- Checks executed: `php artisan test` — **194 passed, 0 skipped (854
  assertions)** with `RUN_MARIADB_CONCURRENCY=1`; `pint` — passed; `tsc -b`,
  `npm run build`, `npm run lint` — passed; live API smoke — 19/19 (booking
  201 → duplicate 409 → confirm/complete → review → approve → report →
  duplicate 422 → admin resolve/dismiss → 403s); 60 screenshots across
  language × theme × viewport confirmed RTL/LTR, Sora branding, and zero
  horizontal overflow on every page.
- Verification data note: the smoke test created live rows (availability
  rule, appointment #52 + review + report for `smoke-client-…@example.com`
  accounts) in the dev database; the smoke review body contains a garbled
  fragment from the test script itself. Test rows remain in the dev DB; say
  the word and they will be removed.




## Production-readiness mission - Phases 1-8 records (2026-10-19)

- Phase 0 (backups): file snapshot `backups/ui-2026-10-19_0827/` (pre-change
  source tree) + verified MariaDB dump
  `backups/db-2026-10-19_0827/consultation_platform.sql`. `BACKUP_README.md`
  documents contents and rollback.
- Phase 1 (private admin path): `config/sora.admin_path` + `AdminPanelController`
  (hash_equals, 404+noindex/no-store, `/admin` always 404) + SPA
  `adminBase()` wiring with DEV-only fallback; production bundle served from
  the API origin via Vite manifest. 4 route tests + 7 feature tests green;
  browser-verified, `/admin` and wrong-path attempts 404.
- Phase 2 (no default creds): one-time `admin:create` command (prompt-based,
  policy min 12 mixed case + digits, refuses duplicates) + production seeder
  guard (`RuntimeException`).
- Phase 3 (consultant provisioning): optional-password admin user creation +
  one-time password-broker setup links (`/admin/users/{user}/password-link`,
  `throttle:admin-actions`) + public `/auth/password-setup`
  (`throttle:auth-password`, single-use, token revocation) +
  `password_set`/`password_set_at` surfaced in resources/API. Browser E2E
  green (provision -> setup link -> set password -> login 200 -> link reuse
  422).
- Phase 4 (bilingual profiles): `name_en/headline_en/bio_en` migrations +
  `ResourceLocalization` fallback chain + admin/consultant editors + localized
  public resources + `translation_missing_en` flags. 5/5
  `ConsultantLocalizationTest`.
- Phase 5 (dual currency): `price_usd_cents` on types + booking snapshots
  (integer cents, no invented conversion), EN booking blocked without USD fee
  (localized), admin pricing USD editor + missing-flag, consultant dashboard
  optional USD field, `fmt.usd` display helper. 7 new
  `DualCurrencyBookingTest` cases (+ unseen `Accept-Language` test-client
  artifact documented and pinned to fa in Persian-flow tests).
- Phase 6 (email verification): `email_verification_tokens` (SHA-256, expiring,
  single-use), notification mail (log driver locally), public verify +
  authenticated throttled resend (`auth-email` 3/min), booking gate
  `assertEmailVerified`, verification banner + `/verify-email` page + resend
  in the SPA. Suite E2E + live log-driver E2E (replay 422) green.
- Phase 7 (UI retheme): semantic-token palette swap to mandated
  `#F8BBD0` / `#B3E5FC` (light + dark), `theme-color` updated, no component
  hex literals; screenshots verified fa/en x light/dark.
- Phase 8 (cleanup + closeout): deleted dev test rows - users 23-27, profile 7
  (cascade), appointment 52, review 2, report 1; final counts users 21 /
  appointments 50 / reviews 0 / reports 0. Full suite
  `RUN_MARIADB_CONCURRENCY=1` **221 passed / 965 assertions / 0 skipped**;
  pint clean; tsc-lint-build clean; 33 final screenshots.
- Open items for deployment: configure real SMTP (`MAIL_*`), set USD fees via
  the admin pricing editor for public-facing types, chunk-split the ~750 kB
  entry, opcache/HTTPS/reverse-proxy hardening at deploy time.

## Phase D — Shared-hosting deployment package (2026-10-19) — DONE

1. Timestamped full backup `backups/deploy-20261019-122307/` (verified by
   robocopy /L: 0 differences on both projects; `sample.html` SHA-256 match).
2. Workspace package `deploy/sora-htdocs-20261019-122307/` (29 MB, 6551
   files): root = htdocs contents; backend core + no-deps vendor in `sora/`.
3. Frontend production rebuild `--base=/build/`, `VITE_API_BASE_URL=/api/v1`
   — zero localhost URLs in bundle.
4. Deployment-copy code adaptations (bootstrap public path + trustProxies,
   filesystems public disk → htdocs/storage, SPA catch-all web routes, mail
   log channel, SPA asset base); production `.env` with fresh APP_KEY,
   randomized ADMIN_PATH, `APP_DEBUG=false`; 3 `.htaccess` layers.
5. Schema-only `sora/database/schema.sql` (22 tables, no data/credentials);
   validated via fresh-DB migration run + round-trip import (FK-safe order).
6. Real Apache validation (isolated instance, XAMPP config untouched):
   30/30 checks PASS after fixes; all scratch artifacts (2 DBs + logs +
   test `.env`) removed; XAMPP Apache config restored byte-identical.
7. Deploy replace-manifest + host-side checklist + limitations + rollback
   written to `deploy/sora-htdocs-20261019-122307/DEPLOY_README.md`;
   architecture record in `ARCHITECTURE.md` §22.

## Phase E - Railway (container) deployment readiness (2026-10-19) - DONE

1. Diagnosed Railpack failure: monorepo root has no build entrypoint; chosen
   architecture = single Dockerfile-based web service (backend + build SPA +
   admin shell, one domain).
2. ROUTING refactor: `backend/routes/web.php` now serves `/` + one runtime
   catch-all (admin shell via exact `hash_equals` on ADMIN_PATH inside
   AdminPanelController; all other GET paths serve the public SPA shell —
   probe-safe, no boot-time env dependency). `bootstrap/app.php` gained
   `trustProxies(at: '*')` (matches the deployed cPanel package).
3. Container files (new, root): `Dockerfile` (2-stage node:22-alpine front
   build --base=/build/ -> php:8.2-apache, pdo_mysql+mbstring+opcache,
   docroot backend/public, composer install --no-dev, storage:link),
   `.dockerignore` (excludes .env*, vendor, node_modules, logs, backups,
   deploy, sample.html, *.zip), `docker/apache/sora.conf`,
   `docker/entrypoint.sh` (Listen=$PORT default 8080; config:cache;
   RUN_MIGRATIONS=true gating for controlled migrations; LF line endings).
4. Tests: full backend suite GREEN — 219 passed / 0 failed / 3 skipped
   (skips are the opt-in real-MariaDB concurrency tests, unchanged); new
   `AdminPanelRouteTest` semantics verified in-suite (shell only on exact
   configured path; unknown paths = identical SPA; empty config disables
   shell; health probe). Fixed test-level issue: SPA shell responses now
   regular (BinaryFileResponse exposed empty body to feature tests).
   Frontend production build verified: `npm run build -- --base=/build/`
   -> index.html references /build/assets/*, .vite/manifest.json emitted.
   `php -l` on changed files PASS.
5. Docs: `RAILWAY_DEPLOY.md` (exact dashboard settings, env vars, controlled
   migration + admin creation procedures, healthcheck, known limitations);
   A23 above.
6. NOT LOCAL-VERIFIED (requires Docker/Railway): image build itself, live
   deploy, Railway MySQL 8 runtime, HTTPS/proxy behavior in the real
   cluster, SMTP delivery. sample.html unchanged; no secrets committed;
   nothing pushed; deploy/ cPanel package untouched.
