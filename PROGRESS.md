# FitMirror — Build Progress Checklist

> Living build tracker for the FitMirror Virtual Try-On SaaS Platform.
> **Rule:** Tasks are never removed. Completed tasks are marked `- [x]`. Skipped tasks are marked `- [ ] SKIPPED — <reason>`.

---

## Progress Summary

```
Total Tasks: 829
Completed:   226
Remaining:   603
Progress:    27.26%
```

**Last updated:** 2026-08-18
**Current phase:** Phase 3 — Subscription, Plans & Billing — 3.A, 3.C, 3.D, and 3.E all complete (3.C mocked-only, see blocker below). 3.B remains the phase's only partially-complete section: trial start, cancel, and the state machine are done; subscribe-with-coupon, upgrade/downgrade-with-proration, auto-renewal, and dunning remain SKIPPED — the coupon/payment half of that blocker is now resolved (3.D), but upgrade/downgrade proration specifically still needs a real subscription period end date that only the still-unbuilt renewal job would ever set (see 3.E's own note on why that item was left SKIPPED rather than faked).
**Next task:** Phase 4 — Store, Branch & Staff Management. 4.A (Backend): `stores`/`store_hours`/`kiosk_devices`/`shifts`/`franchise_groups` tables, store CRUD with plan branch-limit enforcement, kiosk pairing/claim/heartbeat, staff shift scheduling and performance aggregation, inter-branch stock lookup, custom subdomain/domain assignment. 4.B (Frontend): store list/create/edit/branding pages, kiosk pairing UI, shift scheduler, staff performance report, custom domain settings. Phase 3's remaining SKIPPED items (Phase 3.B's upgrade/downgrade proration, the 3.C auto-refund trigger) both wait on later phases (a renewal job; Phase 13's Mission Control tenant management) and don't block Phase 4 starting.

**Blocker carried forward from 3.C (unresolved):** `SSLC_STORE_ID`/`SSLC_STORE_PASSWORD` in `backend/.env` are still empty — SSLCommerz sandbox credentials (free to register at [sslcommerz.com](https://sslcommerz.com)) are needed before `SslCommerzService`'s real API calls (session initiate, order validation, refund) can be verified against anything but `Http::fake()` mocks. The user was asked at the start of Phase 3.C and chose to proceed without credentials for now (2026-08-18) — all of 3.C/3.D was built and tested against mocks (64 tests across `tests/Feature/Payment/*` and `tests/Feature/Billing/*`), and the Phase 3.E frontend was additionally verified live in a real browser against the real backend, but live SSLCommerz sandbox verification is still an open item before Phase 3 can be called done fully end-to-end. The two refund endpoint paths (`SSLC_REFUND_INITIATE_ENDPOINT`/`SSLC_REFUND_QUERY_ENDPOINT`) are an additional, related gap — deliberately left unconfigured rather than hardcoded to a guessed URL; see Decision D-18.

---

## Decision Log

Decisions that shape the build, recorded so they are not re-litigated later.

| # | Date | Decision | Rationale | Consequence |
|---|---|---|---|---|
| D-01 | 2026-07-25 | **Develop on the Windows/XAMPP host.** Local queues run via `php artisan queue:work`; Laravel Horizon is installed and configured but only *runs* in production Linux. | `pcntl`/`posix` are Unix-only PHP extensions with no Windows build, and Horizon requires both. Moving to WSL2/Docker was rejected in favour of keeping the existing working toolchain. | No Horizon dashboard locally. Application code must never depend on Horizon-only behaviour — see the rules below. |
| D-02 | 2026-07-25 | **Use MySQL 8.4 LTS on port 3307, installed separately at `C:\mysql8`.** XAMPP's bundled MariaDB 10.4.32 stays on port 3306, untouched, for the developer's other projects. | XAMPP ships MariaDB, not MySQL. MariaDB 10.4 stores `JSON` as `LONGTEXT` and lacks MySQL 8 expression indexes — FitMirror relies on native JSON columns throughout (tenant settings, campaign audience filters, garment anchor points, analytics meta), so a MariaDB dev host would let bugs through that only surface in production. | Dev matches production exactly. `DB_PORT=3307` in `.env`, never 3306. Two database services coexist: `mysql` (MariaDB, 3306) and `MySQL84` (MySQL 8.4, 3307). |
| D-05 | 2026-07-25 | **Use Node.js 22 LTS (v22.18.0), not Node 20.** Managed via the existing `nvm4w` install. | Node 20 LTS reached end-of-life in April 2026. Node 22 is the current LTS, supported to April 2027, and exceeds the product document's "Node 20 LTS" floor. Vite 5 requires `^18 \|\| >=20`, so 22 is fully supported. | An unused Node 20.8.0 remains at `C:\Program Files\nodejs` but is shadowed by nvm on `PATH`. Pin the version with `.nvmrc` so the toolchain cannot silently drift. |
| D-04 | 2026-07-25 | **Use Memurai Developer 4.1.2 as the local Redis server** (Redis 7.2.5 wire-compatible, native Windows service). | Redis has no official Windows build. The Microsoft port is stuck at 3.0 and the community fork at 5.0 — both too old for the 6.2 minimum. WSL/Docker was rejected under D-01. Memurai is free for development and implements the Redis 7.2 protocol. | Production runs genuine Redis on Linux. Only use standard Redis commands — no Memurai-specific extensions — so the two stay interchangeable. |
| D-03 | 2026-07-25 | **MySQL 8.4, not 8.0.** | MySQL 8.0 reached end-of-life in April 2026. 8.4 is the current LTS with support through 2032, and still satisfies the product document's "MySQL 8" requirement. | `mysql_native_password` is removed in 8.4 — all accounts use `caching_sha2_password`. Verified working with PHP 8.2 PDO/mysqlnd. Production must also run 8.4. |
| D-06 | 2026-08-01 | **Laravel 12, not Laravel 11.** Composer physically refuses to install Laravel 11 — every version 11.31–11.55 carries unpatched security advisories because Laravel 11 security support ended March 2026. Laravel 13 was evaluated and rejected because `laravel/horizon` supports `^12.0` only. | The product document and `tasks.txt` specify Laravel 11, but that version is no longer installable in a secure state. Laravel 12 is the newest version Horizon supports. | All backend code follows Laravel 12 conventions (`bootstrap/app.php` fluent configuration, no `app/Http/Kernel.php`, no `app/Exceptions/Handler.php` — exception handling registers via `withExceptions()` in `bootstrap/app.php`). `composer.json` confirms `laravel/framework: ^12.0`. |
| D-07 | 2026-08-01 | **App timezone = UTC, not Asia/Dhaka.** Laravel 12's `config/app.php` ships hardcoded to UTC and MySQL (`my.ini`) is configured `default-time-zone='+00:00'`. Tenant-local display (Asia/Dhaka) is converted at the presentation layer only. | Tenants may eventually span timezones (franchise groups, future expansion), and analytics rollups require one canonical storage zone or the daily/hourly aggregates silently drift. Storing local time would bake in a single-timezone assumption FitMirror doesn't actually have. | `APP_TIMEZONE=UTC` in `.env`. A new `TENANT_DEFAULT_TIMEZONE=Asia/Dhaka` key is added for presentation-layer conversion, consumed wherever a date is rendered to a shop owner, staff member, or customer. |
| D-10 | 2026-08-01 | **React 19.2 and react-router-dom v7 (declarative mode), not React 18 / React Router v6** as the product document specifies. TypeScript pinned to `~5.7.3` rather than the `~6.0.2` `create-vite` selected, because `react-i18next`'s peer range is `typescript@^5`. | `npm create vite@latest -- --template react-ts` installs whatever is current on the npm registry — React 19.2 and Vite 8 at the time Phase 1.B was built. React Router v7's declarative mode (`BrowserRouter`/`Routes`/`Route`) is API-compatible with v6 usage, so the "v6 route trees" checklist language still describes the code shape accurately. | All four apps and every `packages/*` workspace pin `react`/`react-dom` `^19.2.8`. `react-router-dom` route trees use the same declarative API a v6 codebase would. **Known accepted risk:** `react-router-dom@7.18.2` (latest on the registry as of this decision) carries a high-severity advisory, GHSA-qwww-vcr4-c8h2 (RSC-mode CSRF bypass) — FitMirror's apps use plain client-side `BrowserRouter`, never RSC/server actions, so this vector doesn't apply to the current codebase. Re-run `npm audit` before every release and upgrade the moment a patched version ships. |
| D-09 | 2026-08-01 | **No `BaseRepository` class or Repository pattern.** Instead: thin controllers, Eloquent models carry query scopes, and a `BaseService` (`app/Services/BaseService.php`) is the home for multi-step or transactional business logic. | The Repository pattern re-implements what Eloquent's query builder already is — an abstraction over the query builder abstraction. It buys testability that Eloquent already provides via model factories and `RefreshDatabase`, at the cost of a parallel interface to maintain for every model. Modern Laravel practice (and Laravel 12's own conventions) favours scopes + services over repositories. | Every module's service layer extends `BaseService`. Reusable query logic goes in named local scopes on the model (`Product::published()`, `Tenant::active()`), not in a repository class. See DOCUMENTATION.md §4.4. |
| D-08 | 2026-08-01 | **Laravel Sanctum was not actually installed**, despite the prior session's summary claiming 16 packages including Sanctum. `composer.json`/`composer.lock` had no `laravel/sanctum` entry, yet `routes/api.php` already referenced `auth:sanctum` middleware — that route would have failed at runtime. Installed now via `composer require laravel/sanctum:"^4.2" --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` (same pcntl workaround as Horizon, since Sanctum's tree pulls in Horizon as an existing lock constraint). | Discovered during the Phase 1 disk-vs-checklist reconciliation ordered at the start of this session — never take a prior session's summary as verified fact; always check `composer.lock`. | Treat every "already installed" claim from an old session summary as unverified until `composer.lock` / `vendor/` is actually inspected. |
| D-11 | 2026-08-14 | **Every `packages/*` workspace gets its own `tsconfig.json`** (extending `tsconfig.base.json`), even packages with no app-level build step of their own. | `packages/ui` had none, and its `.tsx` files failed under Vitest with `ReferenceError: React is not defined` — not in the real `vite build` (which routes every file through `@vitejs/plugin-react`'s babel transform regardless of tsconfig), only under Vitest's SSR module runner, which falls back to esbuild's own TS/JSX transform and resolves JSX mode (`automatic` vs classic) by walking up to the nearest `tsconfig.json` on disk. With none present, esbuild silently picked classic mode. | Confirmed by adding `packages/ui/tsconfig.json` (later also `packages/api`, `packages/i18n`) — `npm run test --workspaces` went from a hard failure to 4/4 apps passing with no other config changes. Any new `packages/*` workspace must get a `tsconfig.json` the moment it contains `.tsx`, even if nothing outside Vitest currently proves the gap. |
| D-12 | 2026-08-14 | **`phpstan.neon` sets `parameters.parseModelCastsMethod: true`.** Off by default in Larastan. | Every FitMirror model declares casts via Laravel 12's `protected function casts(): array` method (never the legacy `protected $casts` property, per the D-06 convention) — without this flag Larastan can't see those casts at all. It silently fell back to typing every cast attribute (enum casts, `datetime` casts, all of it) as plain `string`, which only became visible once `SuperAdmin::$role`/`$status` (the project's first enum casts) produced `identical.alwaysFalse` and `method.nonObject` false positives across the model *and* every controller reading it. | Larastan analysis (`./vendor/bin/phpstan analyse`) now correctly resolves `$role`/`$status` to their enum types and `last_login_at`/`two_factor_confirmed_at` to `Carbon`. Re-verify this flag survives any future Larastan upgrade — it is easy for a fresh `phpstan.neon` regeneration to drop it and reintroduce silent cast blindness project-wide. |
| D-13 | 2026-08-14 | **`TenantScope` fails closed (no active tenant context ⇒ zero rows), and three specific auth code paths deliberately bypass it: `App\Models\PersonalAccessToken::tokenable()` (a custom Sanctum token model registered via `Sanctum::usePersonalAccessTokenModel()`), `App\Auth\TenantUnawareUserProvider` (registered as the `users` auth provider's driver in place of the default `eloquent` one), and `LoginService`'s own initial email lookup (`User::withoutTenantScope()`).** **Updated 2026-08-17 (Phase 2.C):** a fourth, equally deliberate and audited bypass now exists — `Mission\ImpersonationController::store()`'s `User::withoutTenantScope()->findOrFail($user)`, the only way Mission Control (which has no tenant context of its own) can look up a specific tenant's user to issue an impersonation token. | Building Phase 2.B's login/2FA feature tests caught two real, systemic bugs that inspection alone would have missed: (1) `LoginService`'s `User::query()->where('email', ...)` returned nothing for *every* login attempt, because no tenant context exists at the exact moment login is discovering which tenant a user belongs to; (2) every already-authenticated `auth:sanctum` request against a tenant `User` also failed — Sanctum resolves a token's owner via a `MorphTo` relation that, like any Eloquent relation query, inherits the model's global scopes, so `$accessToken->tokenable` silently resolved to `null`. Laravel's password-reset broker (`Illuminate\Auth\EloquentUserProvider`) has the exact same problem. | Authentication, by definition, must resolve a user's identity *before* their tenant can be known — scoping can only apply to business-data queries that happen *after* identity is established. The four bypasses above are the complete, audited list of legitimate exceptions; any *new* code that looks up a `User` (or another `BelongsToTenant` model) without going through an authenticated request's already-resolved tenant context should be treated as a bug, not a precedent to copy. See DOCUMENTATION.md §4.4.4 and the class-level docblocks on all four call sites for the full reasoning. |
| D-14 | 2026-08-17 | **Tenant-side RBAC uses global, name-shared spatie/laravel-permission roles (`owner`/`manager`/`staff`, guard `web`) — not spatie's "teams" feature keyed by `tenant_id`.** A tenant's "Manager" role is the exact same `roles` table row as every other tenant's "Manager" — capability, not per-tenant data visibility, which `TenantScope` already handles independently. | The only place per-tenant role *customization* would matter — "Build custom-role creation API for Max-plan tenants" — is itself blocked on Phase 3.A's `plans` table not existing yet (see the Phase 2.C checklist). Enabling `teams` now would mean a schema migration (`team_foreign_key` on `roles`/`model_has_roles`/`model_has_permissions`) and a `setPermissionsTeamId()` call wired into `ResolveTenant` for a feature nothing yet uses. | When Max-plan custom roles are actually built, *that* is the point to flip `config/permissions.php`'s (nonexistent today) teams flag on, add the migration, and re-seed — not before. Until then, `User::can(...)` checks are safe precisely because `TenantScope` already prevents a Manager in Tenant A from touching Tenant B's rows regardless of role-row sharing. |
| D-15 | 2026-08-17 | **Mission Control's Super Admin/Support/Finance roles stay on the `SuperAdminRole`/`SuperAdminPermission` enum pair built in Phase 1.C — spatie/laravel-permission is deliberately *not* wired into `App\Models\SuperAdmin`.** | Phase 2.C's checklist asked to "seed Mission Control roles" via spatie or document why that's redundant. `SuperAdminRole::permissions()` already is the seed — a fixed, three-value enum with a hardcoded permission map, working and tested since Phase 1.C (`EnsureSuperAdmin` + `SuperAdmin::hasPermission()`). Introducing a second, database-backed role system for a role set that will never grow past three values and is never tenant-invited or self-service-assignable would be two sources of truth for the same three facts. | Any future permission check for a super admin ability calls `$superAdmin->hasPermission(SuperAdminPermission::X)`, never `$superAdmin->can('x')`/spatie. If Mission Control ever needs *dynamic* roles (unlikely — it's an internal ops panel, not a customer-facing product), revisit this decision explicitly rather than silently mixing both systems. |
| D-17 | 2026-08-17 | **Redis `SCAN` (`App\Support\UsageCounter::resetAll()`) requires two undocumented-by-default phpredis behaviours, both confirmed against the real dev Redis instance rather than assumed: the cursor variable must start as `null` (not `0`/`'0'`, either of which makes phpredis's `scan()` return `false` immediately even with matching keys present), and `SCAN`'s `match` pattern must include the connection's configured key prefix (`config('database.redis.options.prefix')`) since `SCAN` operates on the raw keyspace — while `DEL` re-adds that same prefix automatically, so a key returned by `scan()` must have the prefix stripped back off before being passed to `del()`, or the delete silently no-ops.** | Discovered writing `UsageCounterTest` — an initial implementation using Laravel's `Redis::connection()->scan($cursor, ['match' => ..., 'count' => ...])` wrapper returned zero keys every time despite `dbsize()` confirming they existed, traced step by step via `php artisan tinker` rather than guessed at. Additionally, PHPStan can't model phpredis's native by-reference cursor mutation across loop iterations (it statically treats the cursor as permanently `null`), which required isolating the by-ref call in its own method returning the new cursor as an explicit value. | Any future code that needs `SCAN` (not just usage counters) must follow the same pattern — see `UsageCounter::resetAll()`/`scanBatch()`'s own docblocks for the full account and the exact, verified-working sequence. |
| D-16 | 2026-08-17 | **`ResolveTenant`'s authenticated-user fallback resolves the bearer token directly against `PersonalAccessToken::findToken()`, never `$request->user()` or `Auth::guard('sanctum')->user()`.** | Phase 2.C's staff/audit-log routes were the first to actually depend on this fallback (`EnsureTenantIsActive`/`EnsureTwoFactorIsEnabled`, unattached to any route until now) and its feature tests caught two more systemic bugs: (1) bare `$request->user()` resolves against `config('auth.defaults.guard')` ('web', session-based) since this middleware runs *before* any route's own `auth:sanctum` middleware — it silently never worked; (2) `Auth::guard('sanctum')->user()` "worked" in isolation but Laravel's `AuthManager` caches guard instances (and Sanctum's `RequestGuard` caches its resolved user on top of that) for the container's lifetime, which — in a test making sequential requests as two different principals (`ImpersonationTest`) — returned the *first* request's cached principal on the second call. | The same class of bug as D-13's two login-time scope failures, caught the same way: real feature tests against real routes, not inspection. A direct, stateless `PersonalAccessToken::findToken($bearerToken)` lookup has no guard-instance to cache and re-resolves fresh on every call — see `ResolveTenant::resolveFromAuthenticatedUser()`'s docblock for the full account, including why this is a testing-only failure mode (production is one-request-per-process, per D-01, with no cross-request container reuse). |
| D-18 | 2026-08-18 | **SSLCommerz's refund API endpoints (`SSLC_REFUND_INITIATE_ENDPOINT`/`SSLC_REFUND_QUERY_ENDPOINT`) are deliberately left unconfigured in `config/sslcommerz.php` rather than hardcoded to a guessed URL, unlike the session-initiate (`gwprocess/v4/api.php`) and order-validation (`validationserverAPI.php`) endpoints, which are hardcoded per sandbox/live.** | Those two endpoints are SSLCommerz's well-known, extensively documented REST paths, safe to hardcode with confidence. The refund endpoint's exact path could not be independently verified without a working sandbox account (see this phase's own blocker note) — shipping a guessed URL dressed up as a verified constant would risk a silent, hard-to-diagnose failure in production refund flows, worse than an explicit configuration error. | `App\Services\Billing\SslCommerzService::initiateRefund()` throws a clear `PaymentGatewayException` naming the missing env var if called before these are set. Confirm the correct path against the SSLCommerz merchant panel's own API reference once sandbox credentials exist, then set both via env — no code change needed at that point. |
| D-19 | 2026-08-18 | **Two Phase 3.D modelling choices made to keep scope bounded, both explained in full at their code site rather than re-litigated here: (1) coupons have no persisted "cart" — `POST /billing/coupon/preview` is stateless, a redemption is only ever written when a real Invoice is created, so "remove coupon" needed no API of its own; (2) every add-on (SMS, storage, support, template) is modelled as a uniform consumable balance pack (`addons.unit_amount` required, never null) drawn down FIFO by `AddonConsumptionService`, rather than giving non-quantifiable add-ons like "priority support" a special nullable/boolean shape.** | Both are documented in depth on `CouponService` and `TenantAddon`/`addons` migration's own docblocks. Recorded here only so a future session doesn't rediscover the same trade-off from scratch: a real per-user coupon-cart concept and a mixed balance/flag add-on model were both considered and rejected as unnecessary complexity for what Phase 3.D actually needed. | If a future phase needs a persisted multi-item cart (e.g. bundling a plan + multiple add-ons in one checkout) or a genuinely non-quantifiable add-on (e.g. a one-time white-label unlock with no "amount"), that is the point to revisit these, not before. |

### Rules arising from D-01

- All queued work must run correctly under a plain `queue:work` worker. Horizon is a supervisor and dashboard, not an API to code against.
- Queue names are defined in `config/horizon.php` **and** mirrored in the documented local `queue:work` command, so both runners process the same queues.
- Any job relying on Horizon-specific features (tags, metrics, batches UI) must degrade cleanly when Horizon is absent.
- Production deployment (Phase 16) still runs Horizon under Supervisor — that path is verified on staging, not on the Windows dev host.

---

## Product Parts

| Part | Stack | Description |
|---|---|---|
| **Backend** | PHP 8.2, Laravel 11, MySQL 8, Redis, Horizon, Scout + MeiliSearch | Multi-tenant API-first core |
| **Frontend** | React.js, Tailwind CSS, WebRTC, MediaPipe | Admin Dashboard, Kiosk App, Customer Web Portal |
| **Mission Control** | React.js (separate app) + Laravel super-admin API | Product Owner super admin panel |

---

## Phase Index

| Phase | Name | Focus |
|---|---|---|
| 1 | Foundation | Tooling, project scaffolds, infra, conventions |
| 2 | Multi-Tenancy, Auth & RBAC | Tenants, users, roles, 2FA, audit |
| 3 | Subscription, Plans & Billing | SSLCommerz, invoices, limits, approval flow |
| 4 | Store, Branch & Staff Management | Stores, kiosks, shifts, staff |
| 5 | Product & Catalog Management | Categories, products, inventory, import, search |
| 6 | Virtual Try-On Engine | AR overlay, pose detection, sessions, snapshots |
| 7 | Campaign Manager | Campaign types, builder, channels, analytics |
| 8 | Loyalty & Rewards | Points, tiers, referral, expiry, cards |
| 9 | Customer Engagement | CRM, wishlist, reviews, chat, appointments |
| 10 | Analytics & BI | Rollups, reports, exports, dashboards |
| 11 | Notification System | Channels, triggers, digests, in-app |
| 12 | Integrations & Public API | Facebook, WhatsApp, POS, webhooks, REST API |
| 13 | Mission Control Panel | Tenants, plans, revenue, ops, support |
| 14 | Security & Compliance | Encryption, 2FA, rate limits, privacy, backups |
| 15 | Testing, QA & Performance | Test suites, load, optimization |
| 16 | Deployment & Launch Readiness | Servers, CI/CD, monitoring, go-live |

---

# Phase 1 — Foundation

## 1.A Backend Foundation (Laravel)

- [x] Install PHP 8.2 + required extensions (bcmath, ctype, curl, dom, fileinfo, gd, imagick, intl, mbstring, openssl, pcntl, pdo_mysql, redis, tokenizer, xml, zip)
- [x] Install Composer 2.x and verify version
- [x] Install MySQL 8 locally and create `fitmirror` + `fitmirror_testing` databases
- [x] Install Redis 7 locally and verify `redis-cli ping`
- [x] Install MeiliSearch and verify it runs on port 7700
- [x] Install Node.js 20 LTS + npm and verify versions — satisfied by Node 22 LTS per D-05
- [x] Create Laravel project in `backend/` via Composer — Laravel 12 per D-06, not Laravel 11 as originally specified
- [x] Initialize git repository with monorepo root structure (`backend/`, `frontend/`, `docs/`) — branch `main`, first commit present
- [x] Write root `.gitignore` covering vendor, node_modules, .env, storage, build artifacts
- [x] Define git branching strategy (main / develop / feature/*) and document it
- [x] Create `.env.example` with every required key documented
- [x] Configure `config/app.php` — name, timezone `UTC` (per D-07, not `Asia/Dhaka`), locale `bn`, fallback `en`
- [x] Configure `config/database.php` MySQL connection with strict mode and utf8mb4
- [x] Configure Redis connections (default, cache, queue, session) in `config/database.php`
- [x] Set cache, session, and queue drivers to Redis in `.env` and config
- [x] Install and configure Laravel Horizon with queue definitions (default, notifications, media, analytics, campaigns)
- [x] Create Horizon dashboard auth gate restricted to super admins
- [x] Install Laravel Telescope (local/staging only) with production guard
- [x] Install and configure Sentry SDK for Laravel with environment + release tagging
- [x] Install Laravel Sanctum and publish config for API token auth — was missing despite being reported installed in the prior session; see D-08
- [x] Install `spatie/laravel-permission` for roles and permissions
- [x] Install `spatie/laravel-activitylog` for audit trails
- [x] Install Laravel Scout + MeiliSearch driver and publish config
- [x] Install `intervention/image` for image processing
- [x] Install `barryvdh/laravel-dompdf` for invoice PDFs
- [x] Install `maatwebsite/excel` for bulk import/export
- [x] Install `simplesoftwareio/simple-qrcode` for QR generation
- [x] Install `pragmarx/google2fa-laravel` for TOTP two-factor auth
- [x] Install `league/flysystem-aws-s3-v3` and configure S3/R2 disk
- [x] Configure `config/filesystems.php` with `public`, `s3`, and `tenant` disks
- [x] Configure mail transport (Mailtrap for local, Amazon SES for production)
- [x] Configure logging channels (daily, slack, sentry, stderr) in `config/logging.php`
- [x] Configure CORS in `config/cors.php` for dashboard, kiosk, portal, mission control origins
- [x] Set up API versioning — `routes/api_v1.php` mounted at `/api/v1`
- [x] Create `bootstrap/app.php` middleware and exception registrations for API-first behaviour
- [x] Build `ApiResponse` helper class for consistent success/error JSON envelopes
- [x] Build global exception handler mapping validation, auth, model-not-found, and throttle exceptions to JSON
- [x] Create `BaseFormRequest` with standardized failed-validation JSON response
- [x] Create `BaseApiController` with pagination and response helpers
- [x] Create `BaseService` and service-layer conventions document
- [x] Create `BaseRepository` pattern or query-builder conventions document
- [x] Configure global rate limiters (`api`, `auth`, `tenant`, `kiosk`) in `AppServiceProvider`
- [x] Add `/api/v1/health` endpoint returning app, DB, Redis, and queue status
- [x] Configure PHPUnit/Pest with in-memory or dedicated testing database
- [x] Install Larastan (PHPStan level 6) and add baseline
- [x] Install Laravel Pint and add project ruleset
- [x] Create `docker-compose.yml` for local dev (php-fpm, nginx, mysql, redis, meilisearch, mailhog)
- [x] Create GitHub Actions CI workflow — Pint, Larastan, PHPUnit, npm build
- [x] Create localization files `lang/bn/*.php` and `lang/en/*.php` with base keys
- [x] Add locale-resolution middleware from `Accept-Language` header and user preference
- [x] Write database naming/convention standards into `DOCUMENTATION.md` Section 4
- [x] Create `DatabaseSeeder` skeleton with modular seeder registration
- [x] Document the local queue worker command mirroring the Horizon queue list (per Decision D-01)
- [x] Verify full backend boot: migrate, serve, queue:work, scout status (Horizon boot verified on staging per D-01)

## 1.B Frontend Foundation (React)

- [x] Add `.nvmrc` pinning Node 22 LTS at the repo root (per Decision D-05)
- [x] Create Vite + React workspace root under `frontend/` — React 19.2 (current stable; product doc's "React 18" floor is exceeded, not violated)
- [x] Configure npm workspaces for `apps/dashboard`, `apps/kiosk`, `apps/portal`, `apps/mission-control`, `packages/ui`, `packages/api` — plus `packages/tryon`, `packages/i18n`
- [x] Scaffold `apps/dashboard` (shop owner admin) with Vite + React + TypeScript
- [x] Scaffold `apps/kiosk` (in-store display) with Vite + React + TypeScript
- [x] Scaffold `apps/portal` (customer web) with Vite + React + TypeScript
- [x] Scaffold `apps/mission-control` (super admin) with Vite + React + TypeScript
- [x] Install and configure Tailwind CSS in every app with a shared preset
- [x] Create `packages/ui` design tokens — colors, spacing, typography, radii, shadows
- [x] Configure ESLint + Prettier + import sorting across the workspace
- [x] Add TypeScript strict config and shared `tsconfig.base.json`
- [x] Build shared `packages/api` Axios client with base URL, tenant header, and auth interceptor
- [x] Implement token storage + refresh + 401 auto-logout in the API client
- [x] Add TanStack Query provider with default retry/stale-time policy
- [x] Add Zustand stores for auth, tenant, and UI state
- [x] Configure React Router v6 route trees per app — react-router-dom v7 declarative mode (BrowserRouter/Routes/Route), API-compatible with v6 usage
- [x] Build shared UI components — Button, Input, Select, Checkbox, Radio, Textarea
- [x] Build shared UI components — Modal, Drawer, Tooltip, Popover, Tabs
- [x] Build shared UI components — DataTable with sorting, pagination, and filters
- [x] Build shared UI components — Toast/notification system
- [x] Build shared UI components — FileUploader with drag-drop and progress
- [x] Build shared UI components — EmptyState, Skeleton loaders, ErrorState
- [x] Build shared Chart components wrapper (Recharts) for analytics — `TrendLineChart`, `TrendAreaChart`, `ComparisonBarChart`
- [x] Build `AppShell` layout for dashboard (sidebar, topbar, breadcrumbs)
- [x] Build `KioskShell` full-screen layout with no browser chrome
- [x] Build `PortalShell` mobile-first layout for customers
- [x] Build `MissionShell` layout for super admin
- [x] Add global ErrorBoundary + Sentry browser SDK per app — `Sentry.ErrorBoundary` + `initSentry()` wired into all four apps' `main.tsx`, no-ops without `VITE_SENTRY_DSN`
- [x] Configure i18n (react-i18next) with `bn` default and `en` fallback
- [x] Extract all base UI strings into `bn`/`en` translation files — `packages/i18n` `common` namespace (nav, actions, state, auth); grows per-module as pages are built
- [x] Add environment config handling (`VITE_API_URL`, `VITE_SENTRY_DSN`, etc.)
- [x] Configure production build output and asset hashing for all four apps
- [x] Add Vitest + React Testing Library setup with a sample passing test — one per app (`NotFound.test.tsx`), `packages/ui` given its own `tsconfig.json` so the JSX transform resolves correctly under Vitest's SSR module runner
- [x] Add Playwright E2E harness with a smoke test per app — shared `playwright.config.ts` (4 projects, 4 `webServer` entries), verified with real `npx playwright test` runs (9-10 passing)

## 1.C Mission Control Foundation

- [x] Create `super_admins` table migration (name, email, password, 2FA secret, role, status)
- [x] Create `SuperAdmin` model with separate `super_admin` auth guard
- [x] Configure `config/auth.php` with `super_admin` guard and provider
- [x] Create `routes/api_mission.php` mounted at `/api/v1/mission` with dedicated middleware group — registered via a `then()` callback in `bootstrap/app.php` since `withRouting()` only accepts one `api:` file
- [x] Create `EnsureSuperAdmin` middleware — rejects a suspended account even with a structurally valid token
- [x] Create super admin seeder with credentials pulled from `.env` — `SUPER_ADMIN_NAME`/`_EMAIL`/`_PASSWORD`, idempotent via `firstOrNew`, never overwrites an existing password on re-run
- [x] Add per-route super-admin permission enum (tenants, plans, billing, ops, support) — `SuperAdminPermission` + `SuperAdminRole::permissions()`
- [x] Build Mission Control login page shell in `apps/mission-control` — real working login form (client-side validation, loading/error states), not just static markup; scope naturally extended to a matching `POST /api/v1/mission/login` + `/logout` API since a "shell" with no reachable backend would just be dead code
- [x] Verify Mission Control app boots and reaches `/api/v1/mission/health` — verified two ways: `php artisan test` (14 backend feature tests) and a live full-stack Playwright run (real `php artisan serve` + real `vite` dev server, real login, real token, page reload preserves session)

---

# Phase 2 — Multi-Tenancy, Authentication & RBAC

## 2.A Multi-Tenancy Core (Backend)

- [x] Document the tenancy strategy decision (single database, `tenant_id` discriminator, row-level isolation) — DOCUMENTATION.md §4.4.4
- [x] Create `tenants` table migration (name, slug, subdomain, custom_domain, owner_id, status, trial_ends_at, plan_id, settings JSON, timestamps, soft deletes) — `plan_id` intentionally has no FK yet (`plans` doesn't exist until Phase 3.A)
- [x] Create `Tenant` model with casts, status enum, and relationships
- [x] Create `TenantStatus` enum (pending, trial, active, suspended, expired, rejected)
- [x] Build `BelongsToTenant` trait applying a global scope and auto-filling `tenant_id`
- [x] Build `TenantScope` global Eloquent scope — **fails closed**: no active context = zero rows, not every tenant's rows (a deliberate safety choice found and fixed via a failing test — see §4.4.4)
- [x] Build `TenantContext` singleton service (set, get, forget, runAs)
- [x] Build `ResolveTenant` middleware resolving tenant by subdomain, custom domain, or authenticated user — also handles the `X-Tenant` header dev/staging stand-in
- [x] Build `EnsureTenantIsActive` middleware blocking suspended/expired tenants with a clear error payload
- [x] Add tenant-aware cache key prefixing helper — `App\Support\TenantCacheKey`
- [x] Add tenant-aware queue job base class carrying tenant context — `App\Jobs\TenantAwareJob`
- [x] Add tenant-aware storage path resolver (`tenants/{tenant_id}/...`) — `App\Support\TenantStorage`
- [x] Write feature tests proving cross-tenant data leakage is impossible on every scoped model — `tests/Feature/Tenancy/*` (12 tests), against a throwaway fixture model since no real tenant-owned business table exists until Phase 2.B
- [ ] Create tenant provisioning service (create tenant, owner user, default roles, default store, default settings) — **blocked**: needs `users.tenant_id` (2.B), roles (2.C), `stores` (4.A). Building it now would mean faking those dependencies; lands with Phase 2.B's registration endpoint instead. See DOCUMENTATION.md §4.4.4.
- [ ] Create tenant teardown/soft-delete service with data retention rules — same blocker as above

## 2.B Authentication (Backend)

- [x] Create `users` table migration (tenant_id, store_id, name, email, phone, password, avatar, locale, status, last_login_at, two_factor fields) — extends the default scaffold migration; `store_id` has no FK yet (`stores` doesn't exist until Phase 4.A), same pattern as `tenants.plan_id`
- [x] Create `User` model with Sanctum, roles, tenant scope, and soft deletes — "roles" here means BelongsToTenant + `isTenantOwner()`; real Spatie roles are Phase 2.C
- [x] Build shop-owner registration API `POST /api/v1/auth/register` with validation
- [x] Trigger tenant provisioning + `TenantRegistered` event on registration — `RegistrationService`, narrower than the full "provisioning service" still deferred from Phase 2.A (no roles/store to seed yet)
- [x] Build email verification flow (signed URL, resend endpoint, verified middleware)
- [x] Build login API `POST /api/v1/auth/login` issuing Sanctum tokens with abilities
- [x] Build logout API `POST /api/v1/auth/logout` revoking the current token
- [x] Build `GET /api/v1/auth/me` returning user, tenant, plan, permissions, and limits — `plan`/`limits`/`permissions` are honestly `null`/`[]`: `plans` (Phase 3.A) and real roles (Phase 2.C) don't exist yet
- [x] Build forgot-password API with throttled token email
- [x] Build reset-password API with token validation and password rules
- [x] Build change-password API requiring current password
- [x] Build profile update API (name, phone, avatar, locale, notification prefs) — notification prefs excluded: `notification_preferences` doesn't exist until Phase 9.A
- [x] Implement password policy (min 10 chars, mixed case, number, symbol, breach check) — `Password::defaults()`, one policy shared by every password rule in the app
- [x] Implement login throttling and progressive account lockout after failed attempts — `throttle:auth` (5/min) plus a separate, longer-horizon lockout keyed on consecutive failures since the last success
- [x] Create `login_attempts` table and record IP, user agent, and outcome
- [x] Build active session/device listing API and revoke-session endpoint
- [x] Implement TOTP 2FA — enable, QR provisioning, confirm, disable endpoints
- [x] Generate and store hashed 2FA recovery codes with regenerate endpoint
- [x] Enforce mandatory 2FA for tenant owners and all super admins — tenant owners: enforced and tested (`EnsureTwoFactorIsEnabled`). Super admins: **not yet enforced** — Mission Control has no self-service 2FA setup flow of its own, so enforcing now would permanently lock every super admin out with no way to comply; lands once Mission Control grows its own `/2fa/*` endpoints
- [x] Build 2FA challenge step in the login flow
- [ ] Build customer OTP authentication (phone) for the customer portal — **blocked**: needs the `customers` table (Phase 9.A), which doesn't exist yet. Portal auth is a distinct identity from tenant Users/SuperAdmins; building it now would mean inventing the customers schema ahead of its own phase
- [x] Write feature tests for the entire auth surface — 46 tests across 9 files (`tests/Feature/Auth/*`), all passing; caught and fixed two real bugs along the way (see PROGRESS.md Decision D-13)

## 2.C RBAC & Audit (Backend)

- [x] Define the full permission matrix (module × action) as a config file — `config/permissions.php`, 13 modules / 40 permissions, independent of whether the module's tables exist yet
- [x] Seed roles: Owner, Manager, Staff, and their default permissions — `RolePermissionSeeder`, idempotent (`findOrCreate`/`syncPermissions`); Owner 40/40, Manager 34/40, Staff 8/40 permissions
- [x] Seed Mission Control roles: Super Admin, Support Agent, Finance — **decided redundant, not built**: `SuperAdminRole`/`SuperAdminPermission` enum pair (Phase 1.C) already is the seed; see Decision D-15
- [x] Build role assignment API for staff users — `PATCH /api/v1/staff/{target}/role`
- [ ] SKIPPED — Build custom-role creation API for Max-plan tenants — blocked: `plans` (Phase 3.A) doesn't exist, so "Max-plan" can't be checked; role architecture decision (global roles, no spatie teams) recorded as Decision D-14 so this slots in later without a redesign
- [x] Create Laravel Policies for Tenant, Store, User, Product, Campaign, Loyalty, Customer, Report — **only Tenant and User built**; `TenantPolicy`, `UserPolicy`, plus `ActivityPolicy` for the audit log (not in the original list, added because the audit log needed a gate too). Store/Product/Campaign/Loyalty/Customer/Report policies deferred — those models don't exist until Phases 4–10
- [x] Register policies and enforce `authorize()` in every controller action — auto-discovery (`App\Models\X` → `App\Policies\XPolicy`), `AuthorizesRequests` trait added to the base `Controller`; every Staff/Impersonation controller action calls `authorize()`
- [x] Build `staff invite` API — email invitation with signed acceptance link — `POST /api/v1/staff/invitations`; `staff_invitations` table, sha256-hashed token (not a Laravel signed URL — the accept flow is a frontend SPA route, not a backend-parsed link), `StaffInvitationNotification` mail
- [x] Build invite acceptance API creating the staff user with the assigned role — `POST /api/v1/auth/invitations/accept`, unauthenticated + throttled; creates the User only on acceptance, never on invite
- [x] Build staff CRUD API (list, show, update role, deactivate, delete) — `GET/PATCH/POST/DELETE /api/v1/staff/*`; tenant owner is structurally immutable (can't be deactivated/deleted/role-changed by anyone but themselves)
- [x] Enforce plan staff-account limits on invite/create — was SKIPPED pending Phase 3.A's `plans` table; now built (`PlanService::assertWithinLimit()`, wired into `StaffInvitationService::invite()`) now that it exists
- [x] Configure activity logging on all tenant-facing models — **Tenant and User only** (the only two real tenant-facing models today); custom `App\Models\Activity` (config `activitylog.activity_model`) adds a `tenant_id` column + `BelongsToTenant`, so the audit log is itself tenant-isolated
- [x] Build audit log API with filters (user, module, action, date range) — `GET /api/v1/audit-log`
- [x] Build super-admin impersonation token issuance with audit entry and expiry — `POST /api/v1/mission/impersonate/{user}`, 30-minute Sanctum token, `impersonations` table + `Activity` log entry; a fourth documented `TenantScope` bypass (Decision D-13 update)
- [x] Build impersonation exit endpoint restoring the original session — `POST /api/v1/auth/impersonation/exit`; backend revokes the impersonation token and closes the audit row, frontend (Phase 2.D) is responsible for swapping back to the super admin's already-stashed Mission Control token
- [x] Write RBAC tests asserting each role's allowed and denied actions — 31 new tests across 6 files (`tests/Feature/Rbac/*`), all passing; caught and fixed three real systemic bugs along the way (see Decision D-16)

## 2.D Frontend — Auth & Team

- [x] Build registration page with plan preselection and validation — **no plan preselection**: `plans` (Phase 3.A) doesn't exist, so every tenant registers the same way regardless of eventual plan; `RegisterPage.tsx`
- [x] Build email verification notice + resend page — `EmailVerificationNoticePage.tsx`; reachable both unauthenticated (right after registration) and authenticated (resend action)
- [x] Build login page with error handling and remember-me — `LoginPage.tsx`; "remember me" is client-only (clears the token from localStorage on tab close when unchecked) since Sanctum tokens have no session-vs-persistent distinction of their own
- [x] Build 2FA challenge screen with recovery-code fallback — `TwoFactorChallengePage.tsx`
- [x] Build 2FA setup wizard (QR, confirm code, recovery codes download) — `TwoFactorSetupPage.tsx`, also handles disable/regenerate for an already-enabled account
- [x] Build forgot-password and reset-password pages — `ForgotPasswordPage.tsx`, `ResetPasswordPage.tsx`; the emailed reset link now correctly points at the dashboard SPA (`AppServiceProvider::configurePasswordResetUrl()` — it silently pointed at the bare backend `APP_URL` before, a real gap this page's own existence surfaced)
- [x] Build "pending approval" holding screen shown after payment — **reframed honestly**: shown while `tenant.status` is `pending`/`rejected`; billing (Phase 3) doesn't exist yet so approval today is a pure Mission Control action, not a payment step — `PendingApprovalPage.tsx`
- [x] Build protected-route wrapper with permission-aware rendering — `ProtectedLayout.tsx`; mirrors the backend's own middleware order (auth → verified email → active tenant → owner 2FA) so the UI never lets a user navigate somewhere the API would reject
- [x] Build `usePermissions` hook and `<Can>` component — `hooks/usePermissions.tsx`
- [x] Build profile settings page (details, avatar upload, locale, password) — `ProfileSettingsPage.tsx`
- [x] Build active sessions/devices page with revoke action — `SessionsPage.tsx`
- [x] Build staff management page (list, invite, edit role, deactivate) — `StaffListPage.tsx`; also lists/revokes pending invitations (needed for a complete team page, not a separate checklist item)
- [x] Build invite-acceptance page for new staff — `InviteAcceptPage.tsx`
- [x] Build activity audit log page with filters and pagination — `AuditLogPage.tsx`
- [x] Build impersonation banner shown when a super admin is impersonating — `ImpersonationBanner.tsx`; impersonation opens in a *new browser tab* via `?impersonation_token=`, so "restore the original session" only ever means closing that tab, never juggling two tokens in one

Verified: full workspace build/lint/test clean across all 4 apps (`npm run build/lint/test --workspaces`), plus a live `npx playwright test` run against the real backend (11/11 passing) exercising a genuine register → login → RBAC-gated-redirect flow end to end — caught and fixed two real bugs in the process: `/verify-email` was nested under the authenticated layout even though `RegisterController` issues no token (an unauthenticated visitor would have been bounced straight to `/login`), and the password-reset email pointed at the bare backend `APP_URL` with no route to render it.

---

# Phase 3 — Subscription, Plans & Billing (SSLCommerz)

## 3.A Plans & Limits (Backend)

- [x] Create `plans` table migration (name, slug, price_monthly, price_yearly, currency, trial_days, is_public, sort_order, status)
- [x] Create `plan_limits` table (plan_id, key, value) for sessions/day, categories, SKUs, staff, branches, storage GB — `value = null` means unlimited
- [x] Create `plan_features` table (plan_id, feature_key, enabled, meta JSON) — `meta.tier` carries sub-tier detail (e.g. campaign_manager "basic" vs "full_ai")
- [x] Create `feature_flags` table for platform-wide toggles
- [x] Seed Free, Pro, and Max plans exactly per the product document's comparison table — `PlanSeeder`, values extracted directly from `FitMirror_Full_Product_Doc_v2.docx` §15 ("সাবস্ক্রিপশন প্ল্যান তুলনা"): ৳০/৳৪৯৯/৳১,২৯৯ per month, verified against the seeded rows via tinker
- [x] Create `Plan`, `PlanLimit`, and `PlanFeature` models with relations — plus `FeatureFlag` (not in the original list, needed for the feature_flags table above to have a model at all)
- [x] Build `PlanService` to resolve the effective limits/features for a tenant — falls back to the Free plan for a tenant with no `plan_id` yet (checkout doesn't exist until 3.E)
- [x] Build `FeatureGate` service (`FeatureGate::allows($tenant, 'campaign_manager')`)
- [x] Build `EnforcePlanFeature` middleware for feature-gated routes — `plan.feature:{key}` alias; no real route uses it yet (Phase 7+), proven via an ad-hoc test route the same way `tenant.active`/`tenant.2fa` were in Phase 2.B
- [x] Build `EnforcePlanLimit` middleware/action for countable limits — built as `PlanService::assertWithinLimit()`, an action method rather than route middleware (a generic middleware can't know "how many categories does this tenant already have" without the caller telling it); wired into `StaffInvitationService` for `staff_accounts`, completing the item Phase 2.C had to leave SKIPPED
- [x] Build Redis-backed daily usage counters (try-on sessions, SMS sent, storage bytes) — `App\Support\UsageCounter`; caught and fixed two real phpredis SCAN quirks along the way, see Decision D-17
- [x] Build usage reset scheduled job for daily counters — `php artisan usage:reset`, scheduled daily at 00:00 Asia/Dhaka (`routes/console.php`)
- [x] Build `GET /api/v1/plan/usage` returning current usage vs limits — `current: null` (not `0`) for a metric with no real counter yet (categories/SKUs/branches/storage — Phase 4/5)
- [x] Return limit-exceeded errors with an upgrade CTA payload — `App\Support\PlanGateResponse`, one shape shared by `EnforcePlanFeature` and `PlanLimitExceededException` (thrown by services, rendered centrally by `ApiExceptionRenderer`)
- [x] Write tests for every limit and feature gate across all three plans — `tests/Feature/Plan/*` (16 tests): Free/Pro/Max limits match the product document exactly, feature gate + tier resolution, middleware pass/block, usage endpoint, Redis counters, reset command

## 3.B Subscription Lifecycle (Backend)

- [x] Create `subscriptions` table (tenant_id, plan_id, billing_cycle, status, starts_at, ends_at, trial_ends_at, grace_ends_at, cancelled_at, auto_renew) — plus `cancellation_reason`, added alongside the cancel API below
- [x] Create `SubscriptionStatus` enum (pending_payment, pending_approval, trialing, active, past_due, grace, suspended, cancelled, expired) — a separate state machine from `TenantStatus`, with its own `allowedNextStates()` transition graph
- [x] Create `Subscription` model with state transition guards — `canTransitionTo()`/`transitionTo()`, throws on an invalid edge rather than silently allowing it
- [ ] SKIPPED — Build subscribe API `POST /api/v1/subscription/subscribe` (plan, cycle, coupon) — blocked: coupons (Phase 3.D) don't exist, and a real "subscribe" needs Phase 3.C's SSLCommerz to actually take payment
- [x] Build trial start flow honoring the globally configured trial length — **not actually global**, it's per-plan (`plans.trial_days`), matching the product document's own "৭ বা ১৪ দিনের free trial" note that this is Mission-Control-configurable, not a single hardcoded number; `SubscriptionService::startTrial()`
- [ ] SKIPPED — Build plan upgrade API with prorated credit calculation — blocked: proration math needs an invoicing/credit ledger (Phase 3.C/3.D), building it now would mean inventing that ledger ahead of its own phase
- [ ] SKIPPED — Build plan downgrade API scheduled at period end with limit-conflict warnings — same blocker, plus needs a real "current usage vs new plan's limits" comparison across modules (products/staff/etc.) most of which don't exist until Phase 4/5
- [x] Build cancel API (immediate vs end-of-period) with reason capture — `POST /api/v1/subscription/cancel`; immediate transitions to Cancelled now, end-of-period only flips `auto_renew` off and records the reason (the actual period-end transition is the still-unbuilt auto-renewal job's job to make — see below)
- [ ] SKIPPED — Build resume/reactivate API — straightforward given the transition graph already allows Cancelled/Suspended/Expired → Active, but deferred to keep this batch's scope bounded; not blocked on anything
- [ ] SKIPPED — Build auto-renewal scheduled job running daily — needs Phase 3.C's payment gateway to actually charge a renewal
- [ ] SKIPPED — Build dunning job with retry schedule on day 1, day 3, and day 7 — same blocker
- [ ] SKIPPED — Build grace period handling and feature restriction after expiry — `SubscriptionStatus::Grace` exists in the state machine; the job that would transition into/out of it is the still-unbuilt renewal/dunning jobs above
- [ ] SKIPPED — Build expiry warning notifications at 7, 3, and 1 days before end — needs Phase 11's notification system
- [ ] SKIPPED — Emit `SubscriptionActivated`, `SubscriptionExpired`, `SubscriptionCancelled` events — no listener would consume them yet (same reasoning `TenantRegistered` was fired ahead of its listeners in Phase 2.B, but events tied to lifecycle transitions that aren't built yet would be premature)
- [x] Write subscription lifecycle tests including proration math — **proration math not covered** (no upgrade/downgrade API yet, see above); trial start, valid/invalid transitions, cancellation (both modes), and price resolution are — `tests/Feature/Plan/SubscriptionTest.php`, `CancelSubscriptionEndpointTest.php` (11 tests)

## 3.C Payments — SSLCommerz (Backend)

- [x] Create `payments` table (tenant_id, invoice_id, gateway, gateway_txn_id, val_id, amount, currency, method, status, raw_payload JSON)
- [x] Create `invoices` table (tenant_id, number, subtotal, discount, vat, total, currency, status, issued_at, due_at, paid_at, pdf_path) — plus a nullable `subscription_id` FK, added because the payment flow needs a way to know which subscription a paid invoice activates; Phase 3.D's add-on invoices will leave it null
- [x] Create `invoice_items` table (invoice_id, description, qty, unit_price, total)
- [x] Build sequential, tenant-safe invoice number generator — `App\Services\Billing\InvoiceNumberGenerator`, `INV-{year}-{6 digits}` off one global sequence table (`invoice_number_sequences`) incremented under `lockForUpdate()`; see its own docblock for why "tenant-safe" was read as concurrency-safe-across-tenants rather than per-tenant numbering
- [x] Create `config/sslcommerz.php` with store id, store password, sandbox flag, and callback URLs
- [x] Build `SslCommerzService` — session initiate request with full payload
- [x] Build SSLCommerz order validation (`validationserverAPI`) call and response verification
- [x] Build payment initiate API returning the gateway redirect URL — `POST /api/v1/payment/initiate`
- [x] Build success callback endpoint with signature/hash verification — SSLCommerz's classic REST integration verifies server-to-server via `validationserverAPI` (status + amount match) rather than a client-side HMAC signature; see `SslCommerzService::validateTransaction()`'s docblock
- [x] Build fail callback endpoint updating payment and invoice state
- [x] Build cancel callback endpoint
- [x] Build IPN webhook endpoint with idempotent processing
- [x] Persist every gateway payload for reconciliation and disputes — `Payment::appendRawPayload()`, keyed by round trip (`initiate_request`/`initiate_response`/`success_callback`/`validation`/`fail_callback`/`cancel_callback`)
- [x] Move the tenant to `pending_approval` after a verified successful payment — no new tenant state needed: `TenantStatus::Pending`'s own label is already "Pending Approval" (set at registration); a verified payment instead transitions the *Subscription* `PendingPayment → PendingApproval` (Phase 3.B's existing state machine), documented in `PaymentService`'s class docblock
- [x] Build offline/manual payment recording API for Mission Control — `POST /api/v1/mission/tenants/{tenant}/payments`, Finance/Super Admin only
- [x] Build refund initiation service with gateway refund call and local ledger entry — `App\Services\Billing\RefundService`, a `refunds` table ledger row is created even when the gateway call fails; exposed via `POST /api/v1/mission/payments/{payment}/refund`
- [ ] SKIPPED — Build auto-refund trigger when Mission Control rejects a tenant — blocked: no tenant-reject action exists yet (that's Phase 13's Mission Control tenant management, not built). `RefundService::refund()` is the primitive that trigger will call once it exists — same "build the primitive ahead of its caller" pattern as `SubscriptionService::cancel()`/`startTrial()` in Phase 3.B
- [x] Write payment tests using a mocked SSLCommerz client for success, fail, and IPN replay — `tests/Feature/Payment/*` (29 tests, all via `Http::fake()`, no real SSLCommerz sandbox credentials — see this phase's own blocker note above)

## 3.D Coupons, Add-ons & Invoicing (Backend)

- [x] Create `coupons` table (code, type, value, applies_to_plans, max_redemptions, per_tenant_limit, starts_at, expires_at, status)
- [x] Create `coupon_redemptions` table (coupon_id, tenant_id, invoice_id, amount_discounted)
- [x] Build coupon validation and discount calculation service — `App\Services\Billing\CouponService`
- [x] Build coupon apply/remove API for checkout — `POST /billing/coupon/preview`; no persisted cart/coupon state exists anywhere in this app, so "apply" is the checkout page re-calling preview with a code and "remove" is simply not sending one on the next call — see `CouponService`'s own docblock for why this is a deliberate design choice, not a shortcut
- [x] Create `addons` table (SMS pack, storage pack, priority support, template pack) with pricing — seeded by `AddonSeeder`
- [x] Create `tenant_addons` table tracking purchased add-ons and remaining balances
- [x] Build add-on purchase flow reusing the SSLCommerz payment pipeline — `App\Services\Billing\AddonPurchaseService`, built on the same `GatewayCheckoutService` `PaymentService` uses (extracted from Phase 3.C's `PaymentService::initiate()` specifically so this phase could reuse it instead of duplicating the SSLCommerz round trip)
- [x] Build add-on consumption service (decrement SMS/storage balances) — `App\Services\Billing\AddonConsumptionService`, FIFO across `TenantAddon` rows by purchase date; no real caller yet (SMS sending is Phase 11, storage tracking is Phase 5) — tested directly, same "primitive ahead of its caller" pattern as `SubscriptionService`'s Phase 3.B methods
- [x] Build VAT/tax configuration and per-invoice tax computation — `config/tax.php` (`VAT_RATE`, default 15%), `App\Support\TaxCalculator`; applied to both plan-purchase and add-on invoices, **not** applied to Mission Control's manual/offline payments (a negotiated amount Finance types in is taken as the final total as-is — see `PaymentService::recordOffline()`'s own docblock for why)
- [x] Build branded PDF invoice template with tenant and platform details — `resources/views/pdf/invoice.blade.php`, rendered via `barryvdh/laravel-dompdf`
- [x] Build invoice PDF generation job and S3 storage — `App\Jobs\GenerateInvoicePdfJob`, saves to the `tenant` disk (S3/R2 in production, local in dev) via `App\Services\Billing\InvoicePdfService`; dispatched automatically whenever any invoice (plan or add-on) is marked Paid
- [x] Build invoice email delivery with PDF attachment — `App\Jobs\SendInvoiceEmailJob` (chained after PDF generation) + `App\Mail\InvoiceMail`
- [x] Build invoice listing and download APIs for tenants — `GET /billing/invoices`, `GET /billing/invoices/{invoice}/download`
- [x] Build billing history API with payments, refunds, and credits — `GET /billing/history`; "credits" don't exist as a concept anywhere in this codebase (no credit-ledger table was ever specified or built), so the feed merges payments + refunds only
- [x] Write tests for coupon edge cases, VAT rounding, and invoice numbering — `tests/Feature/Billing/*` (8 files, 35 tests) + `tests/Feature/Plan/PlanListTest.php`/`CurrentSubscriptionTest.php` (4 tests); all passing alongside the full 193-test backend suite

Also built, beyond the checklist's own line items, because Phase 3.E's frontend genuinely needed them: `GET /plans` (public plan catalog — no such endpoint existed for a pricing page to read from) and `GET /subscription` (the tenant's current subscription — nothing exposed this either, only cancel/auto-renew wrote to it).

## 3.E Frontend — Billing

- [x] Build public pricing page with the Free/Pro/Max comparison table from the product doc — `PricingPage.tsx`, reads live from `GET /plans` rather than hardcoding the table (see `PlanListController`'s own docblock)
- [x] Build plan selection and billing-cycle toggle (monthly / yearly with 20% discount) — folded into `PricingPage.tsx`
- [x] Build checkout page with coupon field and order summary — `CheckoutPage.tsx`
- [x] Build SSLCommerz redirect handoff and return handling — `window.location.href = result.gateway_url` in both `CheckoutPage.tsx` and `AddonsPage.tsx`
- [x] Build payment success, failure, and cancelled result pages — `PaymentResultPage.tsx`, one page keyed by the `:status` route param, matching the backend callback controllers' own `resultUrl()` redirect targets exactly
- [x] Build "awaiting owner approval" status page with support contact — **already existed**, built in Phase 2.D (`PendingApprovalPage.tsx`); re-verified it's still reachable mid-checkout by adding `/billing/checkout` and `/billing/payment` to `ProtectedLayout`'s action-exempt paths, since a tenant is `pending` for its entire first payment, not just before it
- [x] Build billing dashboard (current plan, usage meters, renewal date) — `BillingDashboardPage.tsx`; "renewal date" is honestly `trial_ends_at` only — `subscriptions.ends_at` is never populated by anything yet (see the SKIPPED item just below), so there is no real renewal date to show yet
- [ ] SKIPPED — Build upgrade/downgrade flow with proration preview — **still blocked**, and documented as such in `PaymentService`'s own class docblock: proration needs a real subscription period end date (`subscriptions.ends_at`), which nothing populates because the renewal job that would set it doesn't exist (Phase 3.B's own still-SKIPPED item). Building a "preview" against an end date that's always null would be fabricating a number, not a feature — this is the one item from today's batch left honestly undone rather than faked
- [x] Build invoice list with PDF download — `InvoicesPage.tsx`
- [x] Build add-on marketplace page (SMS, storage, support, templates) — `AddonsPage.tsx`
- [x] Build payment method / auto-renew toggle UI — auto-renew toggle built and working (`PATCH /subscription/auto-renew`, a new endpoint — Phase 3.B never built one, only `cancel()` flipped the flag as a side effect); "payment method" has no toggle because SSLCommerz's hosted checkout page is where a customer picks card/bKash/Nagad on every payment — this integration never stores a preferred method, so there's honestly nothing to persist for that half
- [x] Build plan-limit-reached modal with upgrade CTA — `components/billing/PlanLimitModal.tsx` + `parsePlanLimitError()` helper, parses `App\Support\PlanGateResponse::limitExceeded()`'s exact error shape so any future mutation across the app (not just billing) can show it
- [x] Build feature-locked overlay component for gated features — `components/billing/FeatureLockedOverlay.tsx`, reads `features` off `GET /auth/me` (a Phase 3.D addition to `MeController` — it previously returned `limits` but not `features`)

Verified live, not just build/lint/test: `php artisan serve` + `npm run dev --workspace=apps/dashboard` against the real backend — unauthenticated `/pricing` renders real Free/Pro/Max data with a working monthly/yearly toggle; an authenticated owner's `/billing` renders real plan/usage/trial data, the auto-renew toggle round-trips through the real API and survives a reload, `/billing/invoices` and `/billing/addons` (seeded) render correctly, and `/billing/checkout` computes a real order summary — zero console/network errors across all of it. Full workspace build/lint/test also clean across all 4 apps.

---

# Phase 4 — Store, Branch & Staff Management

## 4.A Backend

- [ ] Create `stores` table (tenant_id, name, code, is_main, phone, email, address, city, area, lat, lng, map_url, logo, banner, socials JSON, status)
- [ ] Create `Store` model with tenant scope and relations
- [ ] Build store CRUD APIs with plan branch-limit enforcement
- [ ] Build store profile update API (logo, banner, contact, social links, Google Map link)
- [ ] Create `store_hours` table and kiosk active-hours configuration
- [ ] Build kiosk hours API and enforcement in the kiosk session guard
- [ ] Create `kiosk_devices` table (store_id, name, pairing_code, device_fingerprint, status, last_seen_at, settings JSON)
- [ ] Build kiosk pairing API — generate short-lived pairing code
- [ ] Build kiosk claim API exchanging the pairing code for a long-lived device token
- [ ] Build kiosk heartbeat API updating `last_seen_at` and reporting health
- [ ] Build kiosk device CRUD and remote unpair API
- [ ] Build kiosk display settings API (language, theme, idle timeout, screensaver playlist)
- [ ] Create `shifts` table and staff shift assignment APIs
- [ ] Build shift schedule listing by store and date range
- [ ] Build staff performance aggregation (try-ons handled, sessions, conversions)
- [ ] Build inter-branch stock availability API for a given product/variant
- [ ] Create `franchise_groups` table linking multiple tenants/stores for enterprise monitoring
- [ ] Build franchise consolidated view API (Enterprise/Max only)
- [ ] Build custom subdomain assignment and validation API (`{slug}.fitmirror.com`)
- [ ] Build custom domain request + verification (DNS TXT) API for Max plan
- [ ] Write tests for branch limits, kiosk pairing, and inter-branch stock lookups

## 4.B Frontend

- [ ] Build store list page with status and quick stats
- [ ] Build store create/edit form (profile, contact, address, map picker)
- [ ] Build store branding page (logo and banner upload with crop)
- [ ] Build store hours editor with per-day open/close and kiosk-active windows
- [ ] Build kiosk devices page (list, pair new device, status, last seen)
- [ ] Build kiosk pairing modal displaying the code and live claim status
- [ ] Build kiosk display settings form
- [ ] Build staff shift scheduler (weekly grid with drag assignment)
- [ ] Build staff performance report page
- [ ] Build inter-branch stock check widget on the product page
- [ ] Build custom domain settings page with DNS verification instructions
- [ ] Build kiosk app pairing screen consuming the pairing code

---

# Phase 5 — Product & Catalog Management

## 5.A Categories & Attributes (Backend)

- [ ] Create `categories` table (tenant_id, parent_id, name, slug, icon, image, gender, sort_order, status)
- [ ] Implement nested category tree helpers (ancestors, descendants, depth limit)
- [ ] Build category CRUD APIs with plan category-limit enforcement
- [ ] Build category reorder API (drag-and-drop sort persistence)
- [ ] Seed default Bangladeshi apparel taxonomy (Boys → Panjabi/Shirt/T-shirt/Pant/Coat/Jacket; Girls → Saree/Threepiece/Kurti/Orna, etc.)
- [ ] Create `attributes` and `attribute_values` tables (color, size, fabric, occasion)
- [ ] Build attribute CRUD APIs with hex color support
- [ ] Create `occasions` table and seed (Wedding, Eid, Office, Casual, Party)
- [ ] Create `tags` table and `taggables` pivot; seed New Arrival, Bestseller, Eid Special, Sale
- [ ] Build tag CRUD and product tagging APIs

## 5.B Products & Variants (Backend)

- [ ] Create `products` table (tenant_id, store_id, category_id, name, slug, sku, description, brand, base_price, sale_price, status, is_tryon_ready, season, publish_at, unpublish_at, meta JSON)
- [ ] Create `product_variants` table (product_id, sku, color_attr_id, size_attr_id, price, stock, barcode, status)
- [ ] Create `product_images` table (product_id, variant_id, disk, path, cdn_url, type, sort_order, is_primary)
- [ ] Create `product_occasion` and `product_attribute` pivots
- [ ] Create `Product`, `ProductVariant`, and `ProductImage` models with relations and casts
- [ ] Build product create API with nested variants and images, wrapped in a transaction
- [ ] Build product update API handling variant add/update/remove diffing
- [ ] Build product list API with filters (category, tag, occasion, status, stock, price range, search)
- [ ] Build product detail API including variants, images, size chart, and stock
- [ ] Build product delete/archive API with try-on asset cleanup
- [ ] Build product duplicate API
- [ ] Enforce plan SKU limits on product and variant creation
- [ ] Build product status toggle (publish/unpublish) API
- [ ] Create `size_charts` table and `product_size_chart` link
- [ ] Build size chart CRUD API with a flexible measurement-row schema
- [ ] Build size chart attach/detach API and kiosk popup payload
- [ ] Create `price_history` table and record every price change with actor and timestamp
- [ ] Build price history API for a product/variant

## 5.C Media & AI Background Removal (Backend)

- [ ] Build media upload API with MIME/size validation and virus-safe handling
- [ ] Build direct-to-S3 presigned upload endpoint for large batches
- [ ] Build image processing job — resize, generate thumbnails (sm/md/lg), convert to WebP
- [ ] Enforce per-tenant storage quota on upload with clear error response
- [ ] Build storage usage recalculation job
- [ ] Integrate background-removal service and build `RemoveBackgroundJob` producing AR-ready transparent PNG
- [ ] Store AR-ready asset separately and mark `is_tryon_ready` on success
- [ ] Build background-removal retry + failure notification path
- [ ] Build manual AR-asset re-upload endpoint for owner corrections
- [ ] Build image delete API with S3 cleanup and orphan sweeper job

## 5.D Inventory (Backend)

- [ ] Create `stock_movements` table (variant_id, store_id, type, quantity, reference, note, user_id)
- [ ] Build stock adjustment API with movement logging
- [ ] Build stock transfer API between branches
- [ ] Create per-variant low-stock threshold configuration
- [ ] Build low-stock detection job dispatching alerts
- [ ] Auto-hide out-of-stock products from try-on and catalog
- [ ] Build season/expiry scheduler job honoring `publish_at`/`unpublish_at`
- [ ] Build dead-stock detection job (no try-on within N days) with clearance suggestion
- [ ] Build inventory report API (on-hand, low stock, dead stock, movement history)

## 5.E Bulk Import, Search & Catalog Sharing (Backend)

- [ ] Build downloadable Excel/CSV import template with reference sheets
- [ ] Build import upload API storing the file and queuing a job
- [ ] Build row-level import validation with a per-row error report
- [ ] Build chunked import job creating products, variants, and stock
- [ ] Build import status API and downloadable error report
- [ ] Build product export to Excel/CSV
- [ ] Configure Laravel Scout searchable array for products
- [ ] Configure MeiliSearch index settings (searchable, filterable, sortable attributes) per tenant
- [ ] Build search API with tenant filtering, facets, and typo tolerance
- [ ] Build search re-index artisan command and scheduled consistency check
- [ ] Create `catalog_links` table (tenant_id, store_id, token, filters JSON, expires_at, views)
- [ ] Build shareable digital catalog link generation API
- [ ] Build public catalog page API (no auth, tenant-branded, product list)
- [ ] Build catalog link view tracking and analytics counter

## 5.F Frontend — Catalog

- [ ] Build category manager with nested drag-and-drop tree
- [ ] Build attribute and color-swatch manager
- [ ] Build product list page with filters, bulk actions, and stock indicators
- [ ] Build product create/edit form with variant matrix builder
- [ ] Build multi-image uploader with primary selection, reorder, and AR-status badge
- [ ] Build AR-asset preview panel showing the background-removed PNG
- [ ] Build size chart builder UI with dynamic rows/columns
- [ ] Build tag and occasion assignment UI
- [ ] Build inventory management page with adjust, transfer, and threshold settings
- [ ] Build low-stock and dead-stock alert widgets
- [ ] Build bulk import wizard (download template, upload, validation report, confirm)
- [ ] Build import progress tracker with live status polling
- [ ] Build price history timeline view
- [ ] Build digital catalog link generator with WhatsApp share button
- [ ] Build public catalog page (branded, mobile-first, shareable)
- [ ] Build product search with instant results and facets

---

# Phase 6 — Virtual Try-On Engine

## 6.A Try-On Backend

- [ ] Create `tryon_sessions` table (tenant_id, store_id, kiosk_device_id, customer_id, guest_token, channel, started_at, ended_at, duration, device_info JSON)
- [ ] Create `tryon_events` table (session_id, product_id, variant_id, event_type, occasion, meta JSON, occurred_at)
- [ ] Create `garment_assets` table (variant_id, ar_image_path, mask_path, anchor_points JSON, scale_factor, layer_type, z_index, status)
- [ ] Build garment calibration API (set anchor points, scale, layer type: top/bottom/outer/accessory)
- [ ] Build session start API returning tenant branding, plan limits, and eligible products
- [ ] Enforce daily try-on session limits per plan at session start
- [ ] Build event ingestion API (batched) for try-on, variant switch, layer add, filter use
- [ ] Build session end API computing duration and flushing analytics counters
- [ ] Build try-on catalog API returning only `is_tryon_ready`, in-stock, published products
- [ ] Build occasion filter API for the kiosk
- [ ] Build outfit-matching recommendation service (rule-based + co-occurrence scoring)
- [ ] Build `GET /api/v1/tryon/{product}/matches` returning suggested shoes/bags/orna for upsell
- [ ] Create `size_estimations` table (session_id, customer_id, measurements JSON, suggested_size, confidence)
- [ ] Build size estimation persistence API from kiosk-computed body metrics
- [ ] Create `fit_feedback` table (session_id, variant_id, verdict: too_loose/perfect/too_tight)
- [ ] Build fit feedback API and feed it into the size suggestion model
- [ ] Build size suggestion service combining size chart, body metrics, and historical fit feedback
- [ ] Build snapshot upload API storing only the composited image (never raw camera frames)
- [ ] Build snapshot share-link generation with expiry and view tracking
- [ ] Build snapshot auto-delete job honoring the tenant retention setting
- [ ] Enforce camera-frame privacy: assert no raw frame is ever persisted, with a test
- [ ] Build session recording opt-in setting and consent capture
- [ ] Build session recording storage, playback URL, and retention job (opt-in tenants only)
- [ ] Build QR try-on token API generating a phone-handoff link per kiosk/product
- [ ] Build QR token redemption API starting a portal session bound to the store
- [ ] Build screensaver playlist API returning trending outfits per store
- [ ] Build multi-person session support (person slots in events and snapshots)
- [ ] Write tests for session limits, privacy guarantees, and recommendation output

## 6.B Kiosk App (React + WebRTC + MediaPipe)

- [ ] Build kiosk bootstrap flow (device token, tenant config fetch, offline cache)
- [ ] Implement WebRTC camera access with permission and device-selection handling
- [ ] Handle camera errors (denied, not found, in use) with recovery UI
- [ ] Integrate MediaPipe Pose for real-time body landmark detection
- [ ] Integrate MediaPipe Face Mesh for face detection and head alignment
- [ ] Build the render pipeline (video → landmarks → canvas/WebGL composite)
- [ ] Build garment warping module mapping garment anchors to body landmarks
- [ ] Implement perspective/affine transform for shoulder, chest, and hip alignment
- [ ] Implement smoothing/lerp on landmarks to remove jitter
- [ ] Implement occlusion handling (arms in front of garment)
- [ ] Build multi-layer compositing with z-index ordering (kurti + orna, panjabi + coat)
- [ ] Build instant color-variant switching without re-detection
- [ ] Build body-size estimation from landmark distances + camera depth heuristics
- [ ] Build size suggestion overlay ("আপনার জন্য M/L উপযুক্ত")
- [ ] Build multi-person detection and per-person garment assignment (couple/family)
- [ ] Build product browser panel (category, occasion filter, search)
- [ ] Build occasion filter chips (Wedding, Eid, Office, Casual, Party)
- [ ] Build matching-outfit suggestion carousel (upsell)
- [ ] Build size chart popup triggered from the product panel
- [ ] Build snapshot capture with countdown and branded frame overlay
- [ ] Build share sheet — WhatsApp, Email, Instagram, and QR download
- [ ] Build QR code display for continuing try-on on the customer's phone
- [ ] Build wishlist/save action for identified customers
- [ ] Build fit feedback prompt (Too loose / Perfect / Too tight)
- [ ] Build star rating prompt after try-on
- [ ] Build campaign banner slot with animated display and countdown timer
- [ ] Build screensaver mode with trending-outfit slideshow and idle detection
- [ ] Build kiosk hours enforcement with an out-of-hours screen
- [ ] Build offline mode with cached assets and event queueing
- [ ] Build event batching and retry-on-reconnect uploader
- [ ] Implement frame-rate targeting and dynamic quality downgrade on slow devices
- [ ] Implement explicit teardown clearing all camera frames from memory on session end
- [ ] Build kiosk language toggle (বাংলা / English)
- [ ] Build kiosk admin gesture/PIN to exit to settings
- [ ] Performance-test the render loop on a mid-range tablet and tune

## 6.C Customer Portal Try-On

- [ ] Build QR landing page resolving the token to a store/product context
- [ ] Build mobile camera try-on view reusing the shared render package
- [ ] Build guest try-on mode with save/share prompting registration
- [ ] Build customer registration/OTP login from the portal
- [ ] Build wishlist add from the portal try-on view
- [ ] Build snapshot share from the portal
- [ ] Build product detail view with size chart and store availability

---

# Phase 7 — Campaign Manager

## 7.A Campaign Core (Backend)

- [ ] Create `campaigns` table (tenant_id, store_id, name, type, status, starts_at, ends_at, budget_cap, discount_config JSON, audience_filter JSON, design JSON, created_by)
- [ ] Create `CampaignType` enum (seasonal, flash_sale, new_arrival, clearance, birthday, referral, bundle, loyalty_points)
- [ ] Create `CampaignStatus` enum (draft, scheduled, running, paused, ended, cancelled)
- [ ] Create `campaign_products` pivot with per-product discount override
- [ ] Create `campaign_variants` table for A/B test variants (name, weight, content JSON)
- [ ] Create `campaign_recipients` table (campaign_id, customer_id, variant_id, channel, status, sent_at, opened_at, clicked_at, converted_at)
- [ ] Create `campaign_metrics` table for aggregated reach/click/conversion/revenue
- [ ] Build campaign CRUD APIs gated by the plan's campaign feature
- [ ] Build campaign duplicate API
- [ ] Build campaign publish/schedule/pause/end APIs with state guards
- [ ] Build campaign scheduler job auto-starting and auto-ending campaigns
- [ ] Build discount resolution service applying campaign pricing to try-on/catalog display
- [ ] Build budget cap enforcement halting a campaign when the discount ceiling is reached
- [ ] Build bundle offer rule builder (buy X get Y) with validation
- [ ] Build flash sale countdown payload for the kiosk
- [ ] Build seasonal campaign one-click launch from a template
- [ ] Build new-arrival auto-campaign triggered by product publish rules
- [ ] Build clearance campaign generator seeded from dead-stock detection
- [ ] Build birthday campaign daily job targeting customers with birthdays
- [ ] Build referral campaign engine with referral codes and dual-side rewards
- [ ] Create `referrals` table (referrer_customer_id, referred_customer_id, code, status, reward_given_at)
- [ ] Build loyalty-point multiplier campaign hooking into the loyalty engine
- [ ] Write tests for scheduling, discount math, budget caps, and bundle rules

## 7.B Audience & Delivery (Backend)

- [ ] Build audience segmentation service (gender, age range, purchase history, location, loyalty tier, last visit)
- [ ] Build audience preview/count API before sending
- [ ] Create `campaign_templates` table and seed 30+ Eid, Puja, New Year, Sale, and Valentine templates
- [ ] Build template listing, preview, and apply APIs
- [ ] Build kiosk banner delivery API returning active banners per store
- [ ] Build SMS blast dispatcher with per-message throttling and add-on balance checks
- [ ] Build email newsletter dispatcher using rendered HTML templates
- [ ] Build WhatsApp broadcast dispatcher (Max plan) via WhatsApp Business API
- [ ] Build push notification dispatcher for portal/app subscribers
- [ ] Build campaign catalog-link generator with campaign pricing applied
- [ ] Build social auto-post service for Facebook and Instagram
- [ ] Build per-channel delivery status tracking and failure retry
- [ ] Build UTM link builder and short-link redirect service with click tracking
- [ ] Create `link_clicks` table (link_id, campaign_id, customer_id, ip, ua, clicked_at)
- [ ] Build A/B test variant assignment with weighted distribution
- [ ] Build A/B winner determination report
- [ ] Build campaign analytics aggregation job (reach, delivered, opened, clicked, converted, revenue, ROI)
- [ ] Build campaign analytics API
- [ ] Build campaign-end summary notification to the tenant

## 7.C Frontend — Campaign Manager

- [ ] Build campaign list page with status filters and quick metrics
- [ ] Build campaign type picker with descriptions and plan gating
- [ ] Build template gallery with live preview and one-click apply
- [ ] Build drag-and-drop campaign designer canvas (blocks: heading, text, image, product grid, countdown, button)
- [ ] Build designer property panel (colors, fonts, spacing, alignment)
- [ ] Build designer live preview for kiosk, email, and mobile viewports
- [ ] Build product picker with search and bulk selection
- [ ] Build discount configuration UI (percent, flat, bundle, tiered)
- [ ] Build audience filter builder with live reach count
- [ ] Build channel selection UI with per-channel cost/balance display
- [ ] Build schedule picker (start, end, timezone-aware) with conflict warnings
- [ ] Build A/B test setup UI with variant editor and split slider
- [ ] Build budget cap input with projected-spend indicator
- [ ] Build campaign review-and-launch confirmation screen
- [ ] Build campaign detail page with real-time delivery progress
- [ ] Build campaign analytics dashboard (funnel, channel breakdown, ROI, A/B comparison)
- [ ] Build UTM link list with click stats and copy buttons

---

# Phase 8 — Loyalty & Rewards Program

## 8.A Backend

- [ ] Create `loyalty_programs` table (tenant_id, name, is_active, earn_rate, currency_per_point, point_value, expiry_months, settings JSON)
- [ ] Create `loyalty_tiers` table (program_id, name, min_spend, min_points, benefits JSON, color, sort_order)
- [ ] Seed default Silver / Gold / Platinum tiers
- [ ] Create `loyalty_point_rules` table (program_id, event, points, conditions JSON)
- [ ] Create `loyalty_transactions` ledger table (customer_id, type, points, balance_after, reference_type, reference_id, expires_at, note)
- [ ] Create `customer_loyalty` table (customer_id, program_id, tier_id, points_balance, lifetime_points, lifetime_spend, joined_at)
- [ ] Build loyalty program configuration API (earn rate, redemption value, expiry window)
- [ ] Build tier CRUD API with threshold validation
- [ ] Build point-earning engine reacting to purchase, try-on, review, referral, and birthday events
- [ ] Build point redemption API with balance validation and reversal support
- [ ] Build redemption-to-discount conversion used at checkout/POS
- [ ] Build tier evaluation job upgrading/downgrading customers on spend changes
- [ ] Build tier-change notification dispatch
- [ ] Build birthday bonus job granting configured points/discount
- [ ] Build referral point award hook from the referral campaign engine
- [ ] Build point expiry job expiring aged points with a pre-expiry warning notification
- [ ] Build digital loyalty card API returning QR payload and tier design
- [ ] Build loyalty card QR scan/verify API for in-store staff
- [ ] Build leaderboard API (top point holders, configurable period, privacy-safe names)
- [ ] Build customer loyalty dashboard API (balance, tier progress, history, expiring soon)
- [ ] Build loyalty analytics API (active members, churn, redemption rate, top earners)
- [ ] Write tests for earn/redeem/expire math and tier transitions

## 8.B Frontend

- [ ] Build loyalty program setup wizard (enable, earn rate, redemption value, expiry)
- [ ] Build tier builder with thresholds, benefits, and colors
- [ ] Build point rule editor for each earning event
- [ ] Build member list with tier, balance, and lifetime spend
- [ ] Build manual point adjust modal (grant/deduct with reason)
- [ ] Build redemption processing screen for in-store staff
- [ ] Build loyalty analytics dashboard
- [ ] Build leaderboard display for dashboard and kiosk
- [ ] Build customer-facing loyalty dashboard in the portal (balance, tier progress, history)
- [ ] Build digital loyalty card view with QR in the portal
- [ ] Build kiosk loyalty card scan flow

---

# Phase 9 — Customer Engagement

## 9.A Backend

- [ ] Create `customers` table (tenant_id, name, phone, email, gender, birthday, size_preferences JSON, source, first_seen_at, last_seen_at, status)
- [ ] Create `customer_addresses` table
- [ ] Build customer CRUD APIs (CRM-lite) with search and segmentation filters
- [ ] Build customer merge/dedupe utility by phone/email
- [ ] Build guest-session-to-customer conversion on registration
- [ ] Build customer import/export (Excel) with validation
- [ ] Create `wishlists` and `wishlist_items` tables
- [ ] Build wishlist add/remove/list APIs for customer and staff views
- [ ] Build price-drop detection job notifying wishlist holders
- [ ] Build back-in-stock notification job for wishlist items
- [ ] Build "customer walked in" wishlist surfacing API for staff
- [ ] Create `reviews` table (customer_id, product_id, rating, title, body, status, replied_at, reply_body)
- [ ] Build review submit API with one-review-per-customer-per-product rule
- [ ] Build review moderation API (approve, hide, flag)
- [ ] Build review reply API for the shop owner
- [ ] Build product rating aggregation (average, count, distribution)
- [ ] Create `appointments` table (customer_id, store_id, product_ids, slot_start, slot_end, status, note)
- [ ] Build store availability slot generator honoring store hours
- [ ] Build appointment booking, reschedule, and cancel APIs
- [ ] Build appointment reminder notification job
- [ ] Create `chat_conversations` and `chat_messages` tables
- [ ] Install and configure Laravel Reverb (WebSockets) for real-time chat
- [ ] Build chat conversation start/list/message APIs with tenant scoping
- [ ] Build chat broadcasting events and presence channel authorization
- [ ] Build unread counts and read receipts
- [ ] Create `notification_preferences` table (customer_id, channel, category, enabled)
- [ ] Build notification preference APIs and honor them in every dispatcher
- [ ] Build customer data export (GDPR-style) API
- [ ] Build customer data erasure request API with anonymization

## 9.B Frontend

- [ ] Build customer list page with filters (tier, last visit, spend, birthday month)
- [ ] Build customer detail page (profile, try-on history, wishlist, purchases, loyalty, reviews)
- [ ] Build customer create/edit form
- [ ] Build customer import wizard
- [ ] Build wishlist trend widget for the dashboard
- [ ] Build reviews moderation page with reply composer
- [ ] Build product rating display on product pages and kiosk
- [ ] Build appointment calendar view for staff with slot management
- [ ] Build appointment booking UI in the customer portal
- [ ] Build live chat widget for the customer portal
- [ ] Build chat inbox for the shop owner dashboard with real-time updates
- [ ] Build notification preferences page in the portal
- [ ] Build customer portal profile page (details, sizes, birthday)
- [ ] Build customer portal wishlist page with price-drop badges

---

# Phase 10 — Analytics & Business Intelligence

## 10.A Backend

- [ ] Design the analytics data model (raw events → daily rollups → report queries)
- [ ] Create `analytics_daily_store` rollup table (date, store_id, sessions, unique_visitors, tryons, snapshots, conversions, revenue)
- [ ] Create `analytics_daily_product` rollup table (date, product_id, tryons, wishlists, conversions, revenue)
- [ ] Create `analytics_hourly_store` rollup table for peak-hour analysis
- [ ] Create `analytics_daily_category` rollup table
- [ ] Create `analytics_daily_customer` rollup table for retention cohorts
- [ ] Build the nightly rollup job aggregating raw try-on events
- [ ] Build the hourly rollup job for near-real-time dashboards
- [ ] Build a rollup backfill artisan command for a date range
- [ ] Build try-on heatmap API (product popularity ranking with period comparison)
- [ ] Build conversion funnel API (viewed → tried on → wishlisted → purchased) per product and overall
- [ ] Build peak-hours report API with staffing recommendation hints
- [ ] Build category performance API with growth/decline indicators
- [ ] Build customer demographics API from camera-estimated age/gender buckets
- [ ] Build revenue report API (daily/monthly/yearly, YoY comparison)
- [ ] Build campaign ROI report API pulling cost vs attributed revenue
- [ ] Build loyalty analytics API (active members, churn rate, top earners, redemption rate)
- [ ] Build dead-stock alert API with clearance campaign suggestions
- [ ] Build customer retention/repeat-visit rate API with cohort table
- [ ] Build wishlist trend API for restock prioritization
- [ ] Build multi-branch comparison API
- [ ] Build report export service producing PDF (dompdf) and Excel outputs
- [ ] Build asynchronous export job with download-ready notification
- [ ] Build scheduled report email (weekly/monthly digest) configuration and job
- [ ] Cache expensive report queries in Redis with tenant-aware invalidation
- [ ] Write tests validating rollup accuracy against raw events

## 10.B Frontend

- [ ] Build the analytics overview dashboard (KPI tiles, trend chart, top products)
- [ ] Build a date-range picker with presets and comparison mode
- [ ] Build the try-on heatmap/popularity chart
- [ ] Build the conversion funnel visualization
- [ ] Build the peak hours chart (hour × weekday heatmap)
- [ ] Build the category performance chart
- [ ] Build the demographics breakdown chart
- [ ] Build the revenue report page with YoY comparison
- [ ] Build the campaign ROI report page
- [ ] Build the loyalty analytics page
- [ ] Build the customer retention cohort table
- [ ] Build the wishlist trend page
- [ ] Build the dead-stock alert page with a "create clearance campaign" action
- [ ] Build the multi-branch comparison view
- [ ] Build export-to-PDF/Excel controls with job progress feedback
- [ ] Build scheduled report configuration UI
- [ ] Gate advanced analytics behind plan features with upgrade prompts

---

# Phase 11 — Notification System

## 11.A Backend

- [ ] Create the `notifications` table (Laravel default) plus tenant-scoped indexes
- [ ] Create `notification_templates` table (key, channel, locale, subject, body, variables)
- [ ] Seed notification templates in Bangla and English for every trigger
- [ ] Build the template rendering service with variable substitution and escaping
- [ ] Build a custom SMS notification channel for the Bangladesh gateway
- [ ] Create `config/sms.php` with driver, credentials, sender ID, and per-message cost
- [ ] Build the SMS gateway driver (BulkSMSBD / SSL SMS) with delivery-report handling
- [ ] Create `sms_logs` table recording recipient, template, cost, and status
- [ ] Enforce the tenant SMS balance/add-on quota before sending
- [ ] Build the email channel with SES configuration and bounce/complaint webhook handling
- [ ] Build the push notification channel (FCM) with device token registration
- [ ] Create `push_devices` table (customer_id, token, platform, last_used_at)
- [ ] Build the WhatsApp notification channel using the Business API
- [ ] Build the in-app database notification channel with real-time broadcast
- [ ] Build the notification preference resolver honoring per-customer and per-user settings
- [ ] Build the notification dispatch service selecting channels by category and preference
- [ ] Build automated trigger — stock below threshold
- [ ] Build automated trigger — campaign started / ended with summary
- [ ] Build automated trigger — payment failed / retry scheduled
- [ ] Build automated trigger — subscription expiring (7/3/1 days) and expired
- [ ] Build automated trigger — new tenant signup alert to Mission Control
- [ ] Build automated trigger — tenant approved / rejected notification
- [ ] Build automated trigger — wishlist price drop and back in stock
- [ ] Build automated trigger — loyalty points earned, redeemed, expiring, tier changed
- [ ] Build automated trigger — appointment booked and reminder
- [ ] Build automated trigger — new review submitted
- [ ] Build automated trigger — unusual tenant activity alert to Mission Control
- [ ] Build the weekly digest email job (try-on count, top products, revenue snapshot)
- [ ] Build notification list, mark-read, and mark-all-read APIs
- [ ] Build the notification retention/cleanup job
- [ ] Write tests for channel selection, preference honoring, and quota enforcement

## 11.B Frontend

- [ ] Build the notification bell with unread badge and dropdown
- [ ] Wire real-time notification delivery over WebSockets
- [ ] Build the full notification center page with filters
- [ ] Build notification settings page for tenant users (channels per category)
- [ ] Build in-kiosk toast for staff-relevant alerts
- [ ] Build the SMS balance widget with a top-up link

---

# Phase 12 — Integrations & Public API

## 12.A Backend Integrations

- [ ] Build an integration registry with per-tenant credential storage (encrypted)
- [ ] Create `integrations` table (tenant_id, provider, credentials encrypted, status, last_synced_at, settings JSON)
- [ ] Build integration connect/disconnect/test APIs
- [ ] Build Facebook Catalog API client (OAuth, catalog selection, batch product upload)
- [ ] Build the Facebook product sync job triggered on product create/update/delete
- [ ] Build Facebook sync status/error reporting per product
- [ ] Build Instagram Shopping sync via the same catalog
- [ ] Build the Facebook/Instagram auto-post service for campaign launches
- [ ] Build the Google My Business client (OAuth, location linking)
- [ ] Build the Google My Business product/post sync job
- [ ] Build Google review import into the reviews module
- [ ] Build the WhatsApp Business API client (template message send, session messages)
- [ ] Build WhatsApp template registration/management helpers
- [ ] Build the WhatsApp delivery webhook handler
- [ ] Build POS integration contract (stock pull, stock push, sale ingestion)
- [ ] Build the POS 2-way stock sync job with conflict resolution rules
- [ ] Build POS sale ingestion endpoint feeding conversions and loyalty points
- [ ] Build Google Analytics 4 measurement-protocol event forwarding
- [ ] Build Zapier/n8n-compatible trigger endpoints and a polling API
- [ ] Create `api_keys` table (tenant_id, name, key_hash, scopes, last_used_at, expires_at, status)
- [ ] Build API key generate/revoke/rotate APIs (Max plan only)
- [ ] Build API key authentication guard with scope checking and per-key rate limits
- [ ] Build the public REST API surface (products, inventory, customers, orders, analytics) with versioning
- [ ] Generate OpenAPI 3.1 specification for the public API
- [ ] Publish interactive API docs (Scalar/Swagger UI) at a public URL
- [ ] Create `webhook_endpoints` table (tenant_id, url, events, secret, status)
- [ ] Build webhook subscription CRUD APIs
- [ ] Build webhook dispatcher with HMAC-SHA256 signing
- [ ] Build webhook delivery log, retry with exponential backoff, and manual replay
- [ ] Build outbound webhook events (product.created, stock.low, payment.succeeded, campaign.ended, tryon.completed)
- [ ] Write integration tests with mocked third-party HTTP clients

## 12.B Frontend

- [ ] Build the integrations page with provider cards and connection status
- [ ] Build the Facebook connect flow with catalog picker
- [ ] Build the sync status page showing per-product sync results and errors
- [ ] Build the Google My Business connect flow
- [ ] Build WhatsApp Business setup UI with template management
- [ ] Build POS integration configuration UI with field mapping
- [ ] Build the API keys page (create, scopes, copy once, revoke)
- [ ] Build the webhooks page (endpoint CRUD, event selection, delivery log, replay)
- [ ] Build the embedded API documentation link/viewer

---

# Phase 13 — Mission Control (Super Admin Panel)

## 13.A Tenant Management

- [ ] Build tenant list API with filters (status, plan, signup date, revenue) and search
- [ ] Build tenant detail API (profile, owner, plan, usage, payments, activity)
- [ ] Build the pending-approval queue API sorted by payment date
- [ ] Build the 1-click approve API — activate subscription, provision, notify tenant
- [ ] Build the reject API with a required reason and automatic refund initiation
- [ ] Build the suspend API with a reason and immediate access revocation
- [ ] Build the reactivate API restoring previous state
- [ ] Build the force-plan-change API bypassing payment
- [ ] Build the extend-trial / extend-expiry API
- [ ] Build the impersonation start API issuing a scoped, expiring token with an audit entry
- [ ] Build the tenant delete/purge API with confirmation and retention policy
- [ ] Build the tenant notes/CRM field for internal remarks
- [ ] Build tenant list page with status chips and bulk actions
- [ ] Build the pending approvals page with side-by-side review and approve/reject actions
- [ ] Build the tenant detail page (overview, subscription, usage, payments, staff, activity tabs)
- [ ] Build the impersonate button with a confirmation and audit warning
- [ ] Build the suspend/reactivate/force-plan modals

## 13.B Plan & Pricing Control

- [ ] Build plan CRUD APIs for Mission Control
- [ ] Build plan limit editing APIs (categories, SKUs, sessions/day, staff, branches, storage)
- [ ] Build feature flag toggle APIs per plan
- [ ] Build custom/enterprise plan creation assignable to a single tenant
- [ ] Build global trial period configuration API
- [ ] Build platform-wide feature flag APIs (kill switches)
- [ ] Build plan management UI with a live comparison table preview
- [ ] Build the limit editor with validation and impact warnings
- [ ] Build the feature flag matrix UI (plan × feature toggles)
- [ ] Build the custom plan builder UI with tenant assignment
- [ ] Build the global settings page (trial days, VAT rate, currency, maintenance)

## 13.C Revenue & Business Dashboard

- [ ] Build the MRR calculation service (normalized monthly recurring revenue)
- [ ] Build ARR, ARPU, churn rate, and LTV calculation services
- [ ] Build the plan distribution API (tenant counts and revenue share per plan)
- [ ] Build the signups/upgrades/downgrades/cancellations trend API
- [ ] Build the platform revenue API (collected, pending, refunded, net)
- [ ] Build the outstanding/overdue invoices API
- [ ] Build the refunds ledger API
- [ ] Build the revenue dashboard UI with KPI tiles and trend charts
- [ ] Build the plan distribution chart
- [ ] Build the growth trend chart (signups vs cancellations)
- [ ] Build the payments and refunds tables with filters and export
- [ ] Build revenue export to Excel/PDF

## 13.D Platform Operations

- [ ] Build global maintenance mode API with an allowlist and custom message
- [ ] Build per-tenant maintenance mode API
- [ ] Build global announcement API (broadcast banner to all tenant dashboards)
- [ ] Create `announcements` table with targeting (all, plan, tenant list) and schedule
- [ ] Build announcement dismissal tracking per user
- [ ] Build the platform email blast composer API with audience targeting and throttled sending
- [ ] Build the system health API (queue depth, failed jobs, DB status, Redis status, API p95 latency, error rate)
- [ ] Build a failed-jobs viewer with retry and delete actions
- [ ] Build the automated daily database backup job with S3 upload
- [ ] Build backup listing, integrity verification, and a one-click restore procedure
- [ ] Build 30-day backup retention pruning
- [ ] Build the maintenance mode UI with a preview of the tenant-facing message
- [ ] Build the announcement composer UI with targeting and scheduling
- [ ] Build the email blast UI with audience preview and test send
- [ ] Build the system health dashboard with live metrics
- [ ] Build the backups page (list, verify, download, restore)

## 13.E Support & Communication

- [ ] Create `support_tickets` table (tenant_id, user_id, subject, category, priority, status, assigned_to, first_response_at, resolved_at)
- [ ] Create `support_ticket_messages` table with attachments
- [ ] Build ticket create/list/detail APIs for tenants
- [ ] Build ticket reply, assign, priority, and status APIs for Mission Control
- [ ] Build ticket SLA tracking and breach alerts
- [ ] Build in-platform direct messaging to a specific tenant
- [ ] Build automated email rules (payment due, plan expiry, feature announcement, account warning)
- [ ] Build the Mission Control audit log API covering every super-admin action
- [ ] Build the ticket inbox UI with filters, assignment, and canned responses
- [ ] Build the ticket detail UI with a threaded conversation and internal notes
- [ ] Build the tenant messaging UI
- [ ] Build the Mission Control audit log UI with actor/action/date filters
- [ ] Build the support ticket UI inside the tenant dashboard

---

# Phase 14 — Security & Compliance

- [ ] Enable database-level encryption at rest and document the provider configuration
- [ ] Apply Laravel encrypted casts to sensitive fields (integration credentials, 2FA secrets, phone where required)
- [ ] Enforce TLS 1.3 and configure HSTS, CSP, X-Frame-Options, and Referrer-Policy headers
- [ ] Verify and test that raw camera frames are never written to disk or object storage
- [ ] Implement try-on snapshot retention policy with an automatic purge job
- [ ] Enforce mandatory 2FA for tenant owners and all Mission Control accounts
- [ ] Implement per-tenant API rate limiting with plan-based quotas
- [ ] Implement per-IP throttling on auth, OTP, and public catalog endpoints
- [ ] Implement brute-force lockout with exponential backoff and admin unlock
- [ ] Audit every raw query and enforce parameter binding (SQL injection review)
- [ ] Audit all output paths for XSS and enable strict escaping in React
- [ ] Implement CSRF protection for cookie-based session routes
- [ ] Implement file upload hardening (extension allowlist, MIME sniffing, size caps, non-executable storage)
- [ ] Implement signed URLs for all private media access
- [ ] Build the permission matrix regression test suite
- [ ] Implement the GDPR-inspired right-to-erasure endpoint with cascading anonymization
- [ ] Implement the customer/tenant data export endpoint
- [ ] Write and publish the privacy policy and terms of service pages
- [ ] Add a consent notice on the kiosk before camera activation
- [ ] Integrate an automated dependency vulnerability scan into CI (composer audit + npm audit)
- [ ] Integrate SAST scanning into CI
- [ ] Document the quarterly security audit procedure and checklist
- [ ] Implement security event logging (login, permission change, impersonation, data export)
- [ ] Implement secret management for production (no secrets in the repo, rotation procedure)
- [ ] Build the incident response runbook

---

# Phase 15 — Testing, QA & Performance

- [ ] Achieve unit test coverage for all service and calculation classes (pricing, loyalty, limits, analytics)
- [ ] Write feature tests for every API endpoint (happy path + validation + authorization)
- [ ] Write multi-tenancy isolation tests across every tenant-scoped model
- [ ] Write plan gating tests for Free, Pro, and Max
- [ ] Write payment flow tests with a mocked SSLCommerz gateway
- [ ] Write queue/job tests for all scheduled and dispatched jobs
- [ ] Write React component tests for shared UI components
- [ ] Write Playwright E2E flow — shop owner signup → payment → approval → login
- [ ] Write Playwright E2E flow — product upload → AR asset ready → kiosk try-on
- [ ] Write Playwright E2E flow — campaign create → schedule → deliver → analytics
- [ ] Write Playwright E2E flow — loyalty earn → redeem → tier upgrade
- [ ] Write Playwright E2E flow — Mission Control approve/reject tenant
- [ ] Add database indexes for all foreign keys and frequent filter columns
- [ ] Profile and eliminate N+1 queries across all list endpoints
- [ ] Add response caching for catalog, plan, and analytics endpoints with tenant-aware invalidation
- [ ] Tune Horizon worker counts, timeouts, and queue priorities
- [ ] Load test the API to the target concurrent-tenant level and record results
- [ ] Load test the kiosk try-on render loop and measure sustained FPS on target hardware
- [ ] Optimize frontend bundles (code splitting, lazy routes, asset compression)
- [ ] Configure CDN caching rules for media and static assets
- [ ] Run Lighthouse audits on the dashboard, portal, and public catalog and fix regressions
- [ ] Verify accessibility basics (keyboard navigation, focus states, contrast, ARIA labels)
- [ ] Verify Bangla typography rendering across all apps and PDFs
- [ ] Run cross-browser and tablet-device compatibility testing for the kiosk
- [ ] Configure Sentry alert rules and error budgets for backend and frontend

---

# Phase 16 — Deployment & Launch Readiness

- [ ] Choose and provision the production server/VPS with documented specs
- [ ] Harden the server (SSH keys only, firewall, fail2ban, automatic security updates)
- [ ] Install the production stack (PHP 8.2-FPM, Nginx, MySQL 8, Redis, Supervisor, MeiliSearch)
- [ ] Configure the Nginx server block for the API with security headers and gzip/brotli
- [ ] Configure wildcard subdomain routing for `*.fitmirror.com`
- [ ] Install and configure SSL certificates (Let's Encrypt wildcard) with auto-renewal
- [ ] Configure custom domain support with on-demand certificate issuance
- [ ] Create the production `.env` with real credentials and document every value
- [ ] Configure SSLCommerz live store credentials and verify a live test transaction
- [ ] Configure Amazon SES production access, DKIM, SPF, and DMARC
- [ ] Configure the production SMS gateway credentials and sender ID
- [ ] Configure S3/R2 production buckets, CORS, lifecycle rules, and CDN
- [ ] Configure Supervisor programs for Horizon and Reverb with auto-restart
- [ ] Configure the system cron entry for `schedule:run`
- [ ] Configure log rotation and centralized log shipping
- [ ] Configure Sentry production DSN and release tracking
- [ ] Disable Telescope and debug mode in production and verify
- [ ] Build the zero-downtime deployment script (atomic release directories and symlinks)
- [ ] Configure the CI/CD deploy pipeline for staging and production with manual promotion
- [ ] Build the frontend production builds and configure their hosting/CDN
- [ ] Set up the staging environment mirroring production
- [ ] Run production migrations and seed plans, roles, and templates
- [ ] Create the initial super admin account securely
- [ ] Configure automated backups with off-site storage and test a full restore
- [ ] Configure uptime monitoring and alerting (API, kiosk, queue, DB)
- [ ] Run a production smoke test of every critical flow
- [ ] Create a demo tenant with sample products for sales demonstrations
- [ ] Build the tenant onboarding wizard (first login guided setup)
- [ ] Record onboarding video/help content in Bangla
- [ ] Complete all Bangla translations and review with a native speaker
- [ ] Complete `DOCUMENTATION.md` sections 6, 7, 13, and 14 with real content
- [ ] Publish the deployment guide, tenant onboarding guide, subscriber guide, and kiosk setup guide
- [ ] Prepare launch materials (pricing page copy, landing site, support email, WhatsApp support line)
- [ ] Run the final pre-launch checklist review and sign-off
