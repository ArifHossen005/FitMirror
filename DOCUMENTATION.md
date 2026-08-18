# FitMirror — Technical Documentation

> **Living document.** Every section is updated as the corresponding part of the system is built.
> Sections marked _"To be filled as we build"_ contain the final structure and are populated with real content (real commands, real endpoints, real schema) the moment the related task in `PROGRESS.md` is completed. No placeholders are left behind.

**Version:** 0.7.0 (Phase 1 & 2 complete; Phase 3.A Plans & Limits complete, 3.B partially complete)
**Last updated:** 2026-08-17
**Maintainer:** Product Owner

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Requirements](#2-system-requirements)
3. [Local Development Setup](#3-local-development-setup)
4. [Project Structure](#4-project-structure)
5. [Environment Variables Reference](#5-environment-variables-reference)
6. [Database Schema](#6-database-schema)
7. [API Reference](#7-api-reference)
8. [Module Documentation](#8-module-documentation)
9. [Deployment Guide — For Product Owner](#9-deployment-guide--for-product-owner-saas-owner)
10. [Tenant Onboarding Guide — For Product Owner](#10-tenant-onboarding-guide--for-product-owner)
11. [Subscriber Guide — For Shop Owners (Tenants)](#11-subscriber-guide--for-shop-owners-tenants)
12. [Kiosk Setup Guide](#12-kiosk-setup-guide)
13. [Troubleshooting](#13-troubleshooting)
14. [Changelog](#14-changelog)

---

# 1. Project Overview

## 1.1 What FitMirror Is

FitMirror is a multi-tenant **Virtual Try-On SaaS platform** built for clothing retailers in Bangladesh. A customer stands in front of a camera at the store kiosk and an AI engine layers garments onto their body in real time — they see how an outfit looks without physically wearing it.

The business problem: a customer typically tries on 5–8 garments physically, which is slow for them and expensive in staff time for the shop. FitMirror compresses that to roughly 30 seconds on screen, which raises conversion, reduces fitting-room load, and turns the kiosk into a marketing channel.

Shop owners manage everything — products, categories, campaigns, loyalty, analytics — from a single dashboard. The Product Owner (SaaS operator) controls the entire platform from a separate Mission Control panel, including manual approval of every paying tenant.

## 1.2 Tech Stack Summary

| Layer | Technology |
|---|---|
| Backend framework | PHP 8.2 + Laravel 11 (API-first) |
| Database | MySQL 8 |
| Cache, session, queue | Redis 7 + Laravel Horizon |
| Search | Laravel Scout + MeiliSearch |
| Realtime | Laravel Reverb (WebSockets) |
| Auth | Laravel Sanctum (API tokens) + TOTP 2FA |
| Frontend | React 18 + Vite + TypeScript + Tailwind CSS |
| Kiosk camera | WebRTC (`getUserMedia`) |
| AI / AR engine | MediaPipe Pose + Face Mesh + custom garment warping module |
| Payments | SSLCommerz (bKash, Nagad, Rocket, VISA, MasterCard, DBBL) |
| File storage | AWS S3 or Cloudflare R2 (CDN-backed) |
| Email | Amazon SES (production) / Mailtrap (development) |
| SMS | Bangladesh local gateway (BulkSMSBD / SSL SMS) |
| Monitoring | Sentry (errors) + Laravel Telescope (local debug) |
| PDF / Excel | dompdf, Laravel Excel |

## 1.3 Architecture Overview

FitMirror is composed of three deliverables sharing one backend.

```
                        ┌──────────────────────────────┐
                        │      Mission Control         │
                        │   (React — Super Admin)      │
                        │  tenants · plans · revenue   │
                        └──────────────┬───────────────┘
                                       │  /api/v1/mission
                                       │
┌───────────────┐   /api/v1   ┌────────▼────────────────┐   ┌─────────────────┐
│  Dashboard    ├────────────►│                         │◄──┤  MySQL 8        │
│  (Shop Owner) │             │   Laravel 11 API        │   └─────────────────┘
└───────────────┘             │   ─────────────────     │   ┌─────────────────┐
┌───────────────┐             │   Multi-tenant core     │◄──┤  Redis          │
│  Kiosk App    ├────────────►│   RBAC · Queues         │   │  cache/queue    │
│  (WebRTC/AR)  │             │   Events · Webhooks     │   └─────────────────┘
└───────────────┘             │                         │   ┌─────────────────┐
┌───────────────┐             │                         │◄──┤  MeiliSearch    │
│ Customer      ├────────────►│                         │   └─────────────────┘
│ Portal (QR)   │             └───────┬─────────────────┘   ┌─────────────────┐
└───────────────┘                     │                     │  S3 / R2 + CDN  │
                                      │                     └─────────────────┘
                    ┌─────────────────┼──────────────────┐
                    ▼                 ▼                  ▼
              SSLCommerz         Amazon SES         SMS Gateway
              Facebook API       WhatsApp API       Google MyBusiness
```

### 1.3.1 Backend (Laravel)
- **Multi-tenant**, single database with a `tenant_id` discriminator and a global Eloquent scope. Every tenant-owned table carries `tenant_id`; a `BelongsToTenant` trait makes isolation automatic rather than something each query has to remember.
- **RESTful API** under `/api/v1` serving all three frontends. Super-admin routes live under `/api/v1/mission` behind a separate auth guard.
- **Redis** for cache, sessions, and queues; **Horizon** supervises named queues (`default`, `notifications`, `media`, `analytics`, `campaigns`).
- **RBAC** with Owner / Manager / Staff roles inside a tenant and Super Admin / Support / Finance inside Mission Control.
- **Event-driven**: campaign triggers, notification dispatch, inventory alerts, and analytics rollups all hang off domain events rather than controller code.
- **Webhooks** both inbound (SSLCommerz IPN, WhatsApp delivery, SES bounces) and outbound (tenant-configured endpoints, HMAC-signed).

### 1.3.2 Frontend (React)
Four applications in one npm workspace, sharing `packages/ui` and `packages/api`:
- **Dashboard** — the shop owner's full management panel.
- **Kiosk App** — full-screen in-store try-on experience, WebRTC camera + MediaPipe overlay.
- **Customer Portal** — QR-scanned mobile try-on, wishlist, loyalty card, appointments.
- **Mission Control** — super admin panel (see below).

### 1.3.3 Mission Control (Super Admin)
The Product Owner's only control centre: tenant approval queue, plan and limit editing without code changes, feature flags, global revenue KPIs (MRR/ARR/churn/ARPU), maintenance mode, announcements, backups, and support tickets.

## 1.4 Tenant Approval Flow

```
Shop registers → selects plan → pays via SSLCommerz → payment verified
      → tenant status = PENDING_APPROVAL
      → Mission Control receives alert
      → Product Owner reviews
            ├── Approve → subscription activates immediately → tenant notified → dashboard unlocked
            └── Reject (reason required) → automatic refund initiated → tenant notified
```

No account becomes active without an explicit approval action. This is a deliberate product decision, not a technical limitation.

## 1.5 Subscription Plans

| | 🆓 FREE | ⚡ PRO | 👑 MAX |
|---|---|---|---|
| Price / month | ৳0 | ৳499 | ৳1,299 |
| Try-on sessions / day | 50 | 500 | Unlimited |
| Categories | 2 | 10 | Unlimited |
| Products (SKU) | 50 | 500 | Unlimited |
| Staff accounts | 1 | 5 | Unlimited |
| Campaign Manager | ✗ | Basic | Full AI |
| Loyalty Program | ✗ | ✓ | Custom |
| Social media post | ✗ | ✓ | Auto |
| Analytics | Basic | Advanced | Full AI insights |
| Custom branding | ✗ | Logo | White-label |
| API access | ✗ | ✗ | ✓ |
| SSLCommerz payment | ✗ | ✓ | ✓ |
| Multi-branch | ✗ | 3 | Unlimited |
| Storage | 5 GB | 50 GB | 500 GB |
| Support | Community | Email | Dedicated manager |

Annual billing carries a 20% discount. Every limit above is stored in `plan_limits` and editable from Mission Control without a code change or deploy.

---

# 2. System Requirements

## 2.1 Software Versions

| Component | Minimum | Recommended | Notes |
|---|---|---|---|
| PHP | 8.2 | 8.2.x latest | 8.3 acceptable; 8.1 unsupported (Laravel 11 requires ≥ 8.2) |
| Composer | 2.5 | 2.10.2+ | Below 2.10.2 is affected by CVE-2026-59946 |
| MySQL | 8.4 LTS | 8.4.8+ | `utf8mb4_unicode_ci`, strict mode on. **Genuine MySQL — not MariaDB.** 8.0 is EOL as of April 2026. See §2.4.1 |
| Redis | 6.2 | 7.2+ | Cache, session, queue. On Windows use Memurai — see §2.4.3 |
| Node.js | 20 | 22 LTS (22.18.0+) | Node 20 is EOL as of April 2026 — see §2.4.5. Vite 5 requires `^18 \|\| >=20` |
| npm | 10 | 10.9+ | Workspaces required (npm 7+) |
| MeiliSearch | 1.5 | 1.6+ | Product search |
| Nginx | 1.22 | 1.24+ | Production |
| Supervisor | 4.2 | 4.2+ | Horizon + Reverb process control |

## 2.2 Required PHP Extensions

`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `imagick`, `intl`, `json`, `mbstring`, `openssl`, `pcntl`, `pdo`, `pdo_mysql`, `redis`, `tokenizer`, `xml`, `zip`

Verify with:

```bash
php -m
```

| Extension | Needed for |
|---|---|
| `bcmath` | Proration, VAT, and loyalty point arithmetic without float drift |
| `ctype`, `tokenizer`, `xml`, `dom`, `json`, `openssl`, `pdo`, `mbstring`, `fileinfo`, `curl` | Laravel 11 framework baseline |
| `gd` | Thumbnails, QR codes, snapshot compositing |
| `imagick` | Product image processing, AR-ready PNG handling, PDF invoice rasterisation |
| `intl` | Bangla/English locale formatting, currency, and date localisation |
| `pdo_mysql` | MySQL driver |
| `redis` | Cache, session, and queue driver (phpredis, faster than a userland client) |
| `zip` | Excel/CSV bulk import and export |
| `pcntl` + `posix` | **Laravel Horizon only.** Unix-only — see the platform note below |

### 2.2.1 Platform note — `pcntl` on Windows

`pcntl` and `posix` are **Unix-only PHP extensions**. They have never been built for Windows and cannot be installed on XAMPP/WAMP. Laravel Horizon requires both, so:

| Environment | Queue runner |
|---|---|
| Windows host (XAMPP) | `php artisan queue:work --queue=high,default,notifications,media,analytics,campaigns` |
| WSL2 / Linux / macOS / Docker | `php artisan horizon` (full dashboard and metrics) |
| Production (Ubuntu) | `php artisan horizon` under Supervisor |

Everything else in the stack behaves identically on Windows. Only the Horizon supervisor and its dashboard are unavailable there.

> **Project decision D-01 (2026-07-25): development runs on the Windows/XAMPP host.**
> Horizon is installed and configured as a dependency, but is only *executed* in production. Local queue processing uses `queue:work`. WSL2 and Docker remain available as fallbacks if the Horizon dashboard is ever needed for debugging, but they are not part of the standard workflow.
>
> Consequences the code must respect:
> - Every queued job must run correctly under a plain `queue:work` worker. Horizon is a supervisor and dashboard, not an API to write against.
> - The queue list in `config/horizon.php` and the documented local `queue:work` command are kept in sync, so both runners process the same queues in the same priority order.
> - Jobs must not depend on Horizon-only features (tags, metrics, the batches UI) for correctness.
> - The Horizon-under-Supervisor path is verified on staging during Phase 16, never on the Windows dev host.

**Local queue worker command (Windows development):**

```bash
php artisan queue:work --queue=high,default,notifications,media,analytics,campaigns --tries=3 --timeout=120
```

Restart the worker after any code change — unlike `queue:listen`, `queue:work` keeps the application booted in memory:

```bash
php artisan queue:restart
```

## 2.2.2 Verified Development Machine (current)

Recorded 2026-07-25 on the primary development host.

| Component | Version | Status |
|---|---|---|
| OS | Windows 11 Pro 26200 | ✅ |
| PHP | 8.2.12 (Thread Safe, x64, VC2019/vs16) via XAMPP at `C:\xampp\php` | ✅ meets ≥ 8.2 |
| `php.ini` | `C:\xampp\php\php.ini` | ✅ |
| Composer | 2.10.2 | ✅ meets ≥ 2.5, no security advisories |
| Git | 2.43.0 | ✅ |
| Extensions | 17 of 18 present | ✅ |
| `pcntl` | Not available | ⚠️ Unix-only — see 2.2.1 |

Extensions installed during setup on this host:

| Extension | Version | How it was installed |
|---|---|---|
| `intl` | 8.2.12 (ICU 71.1) | DLL already shipped with XAMPP; enabled by uncommenting `extension=intl` in `php.ini` |
| `redis` | 6.3.0 | PECL `php_redis-6.3.0-8.2-ts-vs16-x64` |
| `imagick` | 3.8.1 (ImageMagick 7.1.1-46 Q16 x64) | PECL `php_imagick-3.8.1-8.2-ts-vs16-x64` |

### 2.2.3 Installing PECL Extensions on Windows/XAMPP

The PECL build **must** match the PHP build on four axes — version, thread safety, compiler, and architecture. Read them from `php -v` and `php -i`:

```bash
php -v
php -r "echo (PHP_ZTS ? 'ts' : 'nts'), ' ', PHP_INT_SIZE*8, '-bit', PHP_EOL;"
```

For PHP 8.2.12 Thread Safe x64 built with Visual C++ 2019, the correct asset suffix is `-8.2-ts-vs16-x64.zip`. A mismatched DLL loads silently as nothing, or crashes the CLI.

**Redis** — download from `https://windows.php.net/downloads/pecl/releases/redis/6.3.0/`, then:

1. Copy `php_redis.dll` into `C:\xampp\php\ext\`
2. Add `extension=redis` to `C:\xampp\php\php.ini`
3. Verify: `php -r "echo phpversion('redis');"`

**Imagick** — download from `https://windows.php.net/downloads/pecl/releases/imagick/3.8.1/`, then:

1. Copy `php_imagick.dll` into `C:\xampp\php\ext\`
2. Copy the remaining 167 DLLs (`CORE_RL_*.dll`, `IM_MOD_RL_*.dll`, `FILTER_*.dll`) into `C:\xampp\php\` — the PHP root, **not** `ext\`. These are the ImageMagick runtime and must sit on the DLL search path.
3. Add `extension=imagick` to `C:\xampp\php\php.ini`
4. Verify: `php -r "print_r((new Imagick())->getVersion());"`

Always back up `php.ini` before editing it:

```bash
cp /c/xampp/php/php.ini /c/xampp/php/php.ini.backup
```

## 2.3 Operating System

- **Development:** Windows 11 (WSL2 recommended), macOS 13+, or Ubuntu 22.04+
- **Production:** Ubuntu 22.04 LTS or 24.04 LTS (documented target)

## 2.4 Server Specifications

| Scale | vCPU | RAM | Storage | Notes |
|---|---|---|---|---|
| Launch (up to ~50 tenants) | 4 | 8 GB | 100 GB SSD | Single VPS, all services co-located |
| Growth (50–300 tenants) | 8 | 16 GB | 250 GB SSD | Separate MySQL host, Redis co-located |
| Scale (300+ tenants) | 8+ app / 8 db | 16 GB+ each | 500 GB SSD | Load-balanced app tier, managed MySQL, managed Redis, object storage offloaded to S3/R2 |

Media storage does not count against server disk — all product images, AR assets, and snapshots live in S3/R2 behind a CDN.

## 2.4.1 Database Engine — MySQL 8.4, Not MariaDB (Decision D-02 / D-03)

**XAMPP does not ship MySQL.** It bundles **MariaDB 10.4.32**, which despite the `mysql` command name and port 3306 is a different database engine. FitMirror requires genuine MySQL 8 for these reasons:

| Concern | MySQL 8.4 | MariaDB 10.4 | Impact on FitMirror |
|---|---|---|---|
| `JSON` column type | Native binary JSON with validation | Alias for `LONGTEXT` + CHECK constraint | FitMirror stores JSON throughout — `tenants.settings`, `campaigns.audience_filter`, `campaigns.design`, `garment_assets.anchor_points`, `size_estimations.measurements`, `payments.raw_payload`. Behaviour and storage differ. |
| Expression / functional indexes | Supported | Not supported (generated columns only) | Analytics and JSON-path lookups |
| Default collation family | `utf8mb4_0900_*` available | Not available | We pin `utf8mb4_unicode_ci`, which exists in both, so this is neutral |
| Window functions, CTEs | Yes | Yes (10.2+) | Analytics rollups — neutral |

Developing against MariaDB while deploying to MySQL 8 would allow a class of bugs that only appear in production. So MySQL 8.4 is installed **alongside** XAMPP's MariaDB rather than replacing it:

| Service | Engine | Port | Install path | Purpose |
|---|---|---|---|---|
| `mysql` | MariaDB 10.4.32 | 3306 | `C:\xampp\mysql` | XAMPP default — left untouched for other projects |
| `MySQL84` | MySQL 8.4.8 LTS | **3307** | `C:\mysql8` | **FitMirror** |

**Always use port 3307 for FitMirror.** `DB_PORT=3307` in `.env`.

**Why 8.4 and not 8.0:** MySQL 8.0 reached end-of-life in April 2026. 8.4 is the current LTS, supported through 2032. One consequence: `mysql_native_password` is removed in 8.4, so all accounts use `caching_sha2_password`. This is verified working with PHP 8.2's PDO/mysqlnd — no configuration needed.

## 2.4.2 Installing MySQL 8.4 on Windows (One-Time Setup)

Performed 2026-07-25. Reproduce with these exact steps.

**1. Download and extract** (248 MB, no installer required):

```bash
curl -L -o mysql-8.4.8-winx64.zip https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.8-winx64.zip
unzip mysql-8.4.8-winx64.zip -d /c/
mv /c/mysql-8.4.8-winx64 /c/mysql8
```

**2. Create `C:\mysql8\my.ini`:**

```ini
[client]
port=3307
default-character-set=utf8mb4

[mysql]
default-character-set=utf8mb4

[mysqld]
basedir=C:/mysql8
datadir=C:/mysql8/data
port=3307

character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci

sql_mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
default-time-zone='+00:00'

default-storage-engine=INNODB
innodb_buffer_pool_size=512M
innodb_redo_log_capacity=268435456
innodb_flush_log_at_trx_commit=2
innodb_file_per_table=1

max_connections=200
max_allowed_packet=64M
wait_timeout=28800
interactive_timeout=28800

log-error=C:/mysql8/data/mysql-error.log
slow_query_log=1
slow_query_log_file=C:/mysql8/data/mysql-slow.log
long_query_time=2

local_infile=1
```

Config decisions worth knowing:
- **`default-time-zone='+00:00'`** — the database stores UTC. All timezone conversion to `Asia/Dhaka` happens in the application layer. This is what keeps analytics correct once tenants exist in more than one timezone.
- **`sql_mode`** includes `STRICT_TRANS_TABLES` (bad data raises an error instead of being silently truncated) and `ONLY_FULL_GROUP_BY` (catches ambiguous `GROUP BY` queries during development rather than in production).
- **`max_allowed_packet=64M`** — the bulk Excel/CSV product import sends large payloads.
- **`innodb_flush_log_at_trx_commit=2`** — faster local development. Production uses the default `1` for full ACID durability.

**3. Initialize the data directory** (run in an elevated shell):

```bash
/c/mysql8/bin/mysqld --defaults-file=C:\\mysql8\\my.ini --initialize-insecure --console
```

**4. Register and start the Windows service:**

```bash
/c/mysql8/bin/mysqld --install MySQL84 --defaults-file=C:\\mysql8\\my.ini
net start MySQL84
```

**5. Set the root password:**

```bash
/c/mysql8/bin/mysql --host=127.0.0.1 --port=3307 --user=root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'FitMirror@2026!';"
```

Then create the databases and application user as shown in §3.4.

### Service Management

```bash
net start MySQL84          # start
net stop MySQL84           # stop
sc query MySQL84           # status
```

The service is set to **Automatic** start, so MySQL is available after a reboot without intervention.

### Local Development Credentials

> ⚠️ These are **local-only** credentials for a server bound to `127.0.0.1`. They must never be reused in staging or production, where credentials come from the secret manager (see §9.7).

| Account | Password | Use |
|---|---|---|
| `root@localhost` | `FitMirror@2026!` | Administration only |
| `fitmirror@127.0.0.1` | `FitM1rror#Dev2026` | Application account used by `.env` |

### Verified Configuration

Confirmed 2026-07-25 on the development host:

| Check | Result |
|---|---|
| Server version | 8.4.8 |
| Port | 3307 (MariaDB still on 3306, unaffected) |
| `fitmirror` schema | `utf8mb4` / `utf8mb4_unicode_ci` |
| `fitmirror_testing` schema | `utf8mb4` / `utf8mb4_unicode_ci` |
| `sql_mode` | `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` |
| Server timezone | `+00:00` (UTC) |
| Default engine | InnoDB |
| `max_allowed_packet` | 64 MB |
| Bangla (`বাংলা`) round-trip | ✅ correct through insert and select |
| Native JSON functions | ✅ `JSON_OBJECT` / `JSON_EXTRACT` working |
| PHP 8.2 PDO connection | ✅ both schemas, `caching_sha2_password` |
| App-user privilege isolation | ✅ denied access to the `mysql` system schema |

## 2.4.3 Redis on Windows — Memurai (Decision D-04)

**Redis has no official Windows build.** The available options were:

| Option | Version | Verdict |
|---|---|---|
| Microsoft `redis-windows` port | 3.0.504 | ✗ Abandoned, far below the 6.2 minimum |
| `tporadowski/redis` fork | 5.0.14 | ✗ Below the 6.2 minimum |
| WSL2 / Docker | Any | ✗ Rejected under D-01 (native Windows workflow) |
| **Memurai Developer** | **Redis 7.2.5 protocol** | ✅ **Chosen** — native Windows service, free for development |

Memurai implements the Redis 7.2 wire protocol, so `phpredis`, Laravel's cache/queue/session drivers, and `redis-cli` all work unchanged. Production runs genuine Redis on Linux.

> **Rule:** use only standard Redis commands. Never depend on Memurai-specific extensions, so the development and production servers stay interchangeable.

**Install:**

```bash
winget install --id Memurai.MemuraiDeveloper --accept-package-agreements --accept-source-agreements
```

Installs to `C:\Program Files\Memurai`, registers the `Memurai` Windows service on **port 6379** with Automatic start.

**Service management:**

```bash
net start Memurai
net stop Memurai
sc query Memurai
```

**Verify:**

```bash
"/c/Program Files/Memurai/memurai-cli.exe" ping
"/c/Program Files/Memurai/memurai-cli.exe" info server
```

`ping` must return `PONG`. `redis_version` must report `7.2.5`.

### Verified Configuration

Confirmed 2026-07-25:

| Check | Result |
|---|---|
| Service | `Memurai` running, Automatic start |
| Port | 6379 |
| `redis_version` | 7.2.5 (Memurai 4.1.2) |
| `PING` | `PONG` ✅ |
| phpredis extension | 6.3.0, connects ✅ |
| Bangla UTF-8 round-trip | `বাংলা ইউনিকোড` exact match ✅ |
| TTL / `SETEX` | ✅ (session and cache expiry) |
| List operations | ✅ (queue backing) |
| Logical databases | 16 (cache, queue, session get separate indices) |

> Note: `memurai-cli` displays non-ASCII as `?????` because of the Windows console codepage. This is a display artefact only — the stored bytes are intact, as the phpredis round-trip above proves.

## 2.4.4 MeiliSearch Setup (Windows)

MeiliSearch ships a self-contained Windows binary — no installer and no service wrapper.

**Install:**

```bash
mkdir -p /c/meilisearch/data
curl -L -o /c/meilisearch/meilisearch.exe https://github.com/meilisearch/meilisearch/releases/download/v1.50.0/meilisearch-windows-amd64.exe
```

Use the **community** build (`meilisearch-windows-amd64.exe`), not `meilisearch-enterprise-windows-amd64.exe`.

**Configure.** MeiliSearch reads `MEILI_*` environment variables natively, which is more reliable on Windows than command-line flags in a batch file. Set them at machine level (elevated shell):

```powershell
[Environment]::SetEnvironmentVariable('MEILI_MASTER_KEY', '<your-40-char-key>', 'Machine')
[Environment]::SetEnvironmentVariable('MEILI_ENV', 'development', 'Machine')
[Environment]::SetEnvironmentVariable('MEILI_DB_PATH', 'C:\meilisearch\data\meili.ms', 'Machine')
[Environment]::SetEnvironmentVariable('MEILI_HTTP_ADDR', '127.0.0.1:7700', 'Machine')
```

The master key must be at least 16 bytes. The same value goes into `MEILISEARCH_KEY` in `backend/.env`.

**Launcher** — `C:\meilisearch\start-meilisearch.bat`:

```bat
@echo off
cd /d C:\meilisearch
meilisearch.exe
```

**Auto-start at boot** (registers a SYSTEM scheduled task, since MeiliSearch is not a Windows service):

```powershell
$a = New-ScheduledTaskAction -Execute 'C:\meilisearch\start-meilisearch.bat'
$t = New-ScheduledTaskTrigger -AtStartup
$s = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero)
$p = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName 'FitMirror-MeiliSearch' -Action $a -Trigger $t -Settings $s -Principal $p -Force
```

**Manage:**

```bash
schtasks /run /tn FitMirror-MeiliSearch      # start
taskkill /IM meilisearch.exe /F              # stop
curl http://127.0.0.1:7700/health            # check
```

### Verified Configuration

Confirmed 2026-07-25:

| Check | Result |
|---|---|
| Version | 1.50.0 (community build) |
| Port | 7700, bound to `127.0.0.1` only |
| `/health` | `available` ✅ |
| Master key auth | Enforced — unauthenticated requests get 401 ✅ |
| Bangla indexing and search | `পাঞ্জাবি` → 1 exact hit, text intact ✅ |
| English search | `shirt` → 1 hit ✅ |
| Auto-start at boot | `FitMirror-MeiliSearch` scheduled task, verified ✅ |

> **Gotcha:** machine-level environment variables are read from the registry only by *newly created process trees*. An already-running shell will not see them, so `start-meilisearch.bat` launched from a pre-existing terminal may start without configuration. Open a new terminal, or use the scheduled task, which always gets a fresh environment.

## 2.4.5 Node.js Toolchain (Decision D-05)

The product document specifies "Node 20 LTS", but **Node 20 reached end-of-life in April 2026**. FitMirror uses **Node 22 LTS** (supported to April 2027), which exceeds that floor and is fully supported by Vite 5 (`^18 || >=20`).

Node is managed with **nvm for Windows** (`nvm4w`), already installed at `C:\nvm4w`. An older Node 20.8.0 exists at `C:\Program Files\nodejs` but is shadowed by nvm on `PATH` and is not used.

**Verify / switch:**

```bash
nvm list
nvm use 22.18.0
node -v      # v22.18.0
npm -v       # 10.9.3
```

**Install a different version if needed:**

```bash
nvm install 22.18.0
nvm use 22.18.0
```

Pin the version so the toolchain cannot drift — `.nvmrc` at the repository root:

```
22.18.0
```

### Verified Configuration

Confirmed 2026-07-25:

| Check | Result |
|---|---|
| Node | v22.18.0 (LTS) ✅ |
| npm | 10.9.3 ✅ |
| npx | 10.9.3 ✅ |
| corepack | present ✅ |
| Registry reachable | `npm ping` → PONG ✅ |
| npm workspaces | symlinks resolve and dedupe correctly ✅ |

The workspace check matters because the entire frontend (`apps/dashboard`, `apps/kiosk`, `apps/portal`, `apps/mission-control` plus `packages/ui`, `packages/api`, `packages/tryon`, `packages/i18n`) depends on npm workspace linking.

## 2.5 Kiosk Hardware Requirements

| Item | Minimum | Recommended |
|---|---|---|
| Device | Android tablet 10"+ / Windows laptop | 21"+ touchscreen display or all-in-one PC |
| CPU | Quad-core 2.0 GHz | Modern i5 / Snapdragon 8-series |
| RAM | 4 GB | 8 GB |
| Camera | 720p webcam | 1080p wide-angle, mounted at chest height |
| Browser | Chrome 110+ / Edge 110+ (WebRTC + WebGL required) | Latest Chrome in kiosk mode |
| Network | 5 Mbps stable | 20 Mbps, wired or 5 GHz Wi-Fi |
| Lighting | Even front lighting; avoid strong backlight | |

---

# 3. Local Development Setup

> _Populated in full as Phase 1 tasks complete. The command sequence below is the target setup flow and is verified end-to-end at the close of Phase 1._

## 3.1 Clone the Repository

```bash
git clone https://github.com/<org>/fitmirror.git
cd fitmirror
```

## 3.2 Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

## 3.3 Configure `.env`

Edit `backend/.env` and set at minimum the database, Redis, MeiliSearch, and mail values. See [Section 5](#5-environment-variables-reference) for every variable.

## 3.4 Create the Databases

> **Important (decision D-02):** FitMirror uses **MySQL 8.4 LTS on port 3307**, not XAMPP's bundled MariaDB on port 3306. See §2.4.1 for why, and §2.4.2 for the one-time server installation. Always use `--port=3307`.

```bash
/c/mysql8/bin/mysql --host=127.0.0.1 --port=3307 --user=root -p -e "CREATE DATABASE IF NOT EXISTS fitmirror CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/c/mysql8/bin/mysql --host=127.0.0.1 --port=3307 --user=root -p -e "CREATE DATABASE IF NOT EXISTS fitmirror_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Create the least-privilege application user (this is the account `.env` uses — never connect the app as `root`):

```bash
/c/mysql8/bin/mysql --host=127.0.0.1 --port=3307 --user=root -p
```

```sql
CREATE USER IF NOT EXISTS 'fitmirror'@'127.0.0.1' IDENTIFIED BY 'FitM1rror#Dev2026';
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES,
      CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      TRIGGER, EVENT
  ON fitmirror.* TO 'fitmirror'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES,
      CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      TRIGGER, EVENT
  ON fitmirror_testing.* TO 'fitmirror'@'127.0.0.1';
FLUSH PRIVILEGES;
```

The grant deliberately excludes `FILE`, `PROCESS`, `SUPER`, `GRANT OPTION`, and any access to the `mysql` system schema. Verify:

```bash
/c/mysql8/bin/mysql --host=127.0.0.1 --port=3307 --user=fitmirror -p -e "SHOW DATABASES;"
```

Only `fitmirror`, `fitmirror_testing`, `information_schema`, and `performance_schema` should be listed.

## 3.5 Run Migrations and Seeders

```bash
cd backend
php artisan migrate
php artisan db:seed
```

## 3.6 Link Storage and Index Search

```bash
php artisan storage:link
php artisan scout:sync-index-settings
```

## 3.7 Start Supporting Services

Redis (Memurai) and MySQL both run as Windows services with Automatic start, so they are already up after a reboot. Verify rather than launch:

```bash
sc query MySQL84
sc query Memurai
"/c/Program Files/Memurai/memurai-cli.exe" ping
```

MeiliSearch runs in the foreground:

```bash
meilisearch --master-key="<your-local-master-key>"
```

## 3.8 Start the Backend

```bash
cd backend
php artisan serve            # http://127.0.0.1:8000
php artisan reverb:start     # websockets (separate terminal)
```

Queue worker (separate terminal). Per decision D-01 the Windows dev host runs the plain worker; Linux hosts may run Horizon instead:

```bash
# Windows / XAMPP — the standard development path
php artisan queue:work --queue=high,default,notifications,media,analytics,campaigns --tries=3 --timeout=120

# Linux / WSL2 / Docker only (requires pcntl + posix)
php artisan horizon
```

## 3.9 Frontend Setup

`frontend/` is an npm workspace root — one `npm install` at that level resolves and hoists dependencies for all four apps and all `packages/*`. Each app has its own `.env.example` (not one shared file at the workspace root), since `VITE_API_URL` etc. differ between the tenant apps and Mission Control.

```bash
cd frontend
npm install
cp apps/dashboard/.env.example apps/dashboard/.env
cp apps/kiosk/.env.example apps/kiosk/.env
cp apps/portal/.env.example apps/portal/.env
cp apps/mission-control/.env.example apps/mission-control/.env
```

```bash
npm run dev:dashboard         # http://localhost:5173
npm run dev:kiosk             # http://localhost:5174
npm run dev:portal            # http://localhost:5175
npm run dev:mission-control   # http://localhost:5176
```

Or run any app directly: `npm run dev --workspace=apps/dashboard`.

## 3.10 Docker Alternative

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```

## 3.11 Verify the Installation

```bash
curl http://127.0.0.1:8000/api/v1/health
```

## 3.12 Useful Development Commands

```bash
# Backend (run from backend/)
php artisan test                  # run the test suite (against fitmirror_testing MySQL)
./vendor/bin/pint                 # format PHP
./vendor/bin/phpstan analyse      # static analysis (Larastan, level 6)
php artisan telescope:prune       # trim local debug data

# Frontend (run from frontend/)
npx eslint .                      # lint the whole workspace
npx eslint . --fix                # lint + autofix (import sort, etc.)
npx prettier --write .            # format the whole workspace
npm run build                     # build all four apps + packages (--workspaces --if-present)
```

---

# 4. Project Structure

_Folder trees are recorded here as directories are actually created. The structure below is the agreed target layout._

## 4.1 Repository Root

```
fitmirror/
├── backend/              Laravel 11 API
├── frontend/             npm workspace — all four React apps
├── docs/                 diagrams, ADRs, exported schema
├── PROGRESS.md           build checklist
├── DOCUMENTATION.md      this file
└── docker-compose.yml
```

## 4.2 Backend (Laravel)

```
backend/
├── app/
│   ├── Console/Commands/          artisan commands (rollups, reindex, backfill)
│   ├── Enums/                     TenantStatus, SubscriptionStatus, CampaignType, ...
│   ├── Events/                    domain events
│   ├── Exceptions/                domain + API exceptions
│   ├── Http/
│   │   ├── Controllers/Api/V1/    tenant-facing controllers, grouped by module
│   │   ├── Controllers/Mission/   super-admin controllers
│   │   ├── Middleware/            ResolveTenant, EnsureTenantIsActive, EnforcePlanFeature, ...
│   │   ├── Requests/              form requests (validation)
│   │   └── Resources/             API resources (response shaping)
│   ├── Jobs/                      queued jobs
│   ├── Listeners/                 event listeners
│   ├── Models/                    Eloquent models
│   ├── Models/Concerns/           BelongsToTenant and shared traits
│   ├── Notifications/             notification classes per trigger
│   ├── Policies/                  authorization policies
│   ├── Providers/
│   ├── Scopes/                    TenantScope
│   ├── Services/                  business logic, one namespace per module
│   └── Support/                   helpers (ApiResponse, TenantContext, ...)
├── config/                        incl. sslcommerz.php, sms.php, plans.php, permissions.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── lang/{bn,en}/                  translations
├── routes/
│   ├── api_v1.php                 tenant API
│   ├── api_mission.php            super admin API
│   ├── api_public.php             Max-plan public REST API
│   ├── channels.php               broadcast channels
│   ├── console.php                scheduler
│   └── web.php                    callbacks, webhooks, health
├── storage/
└── tests/{Unit,Feature}/
```

## 4.3 Frontend (React workspace)

Built in Phase 1.B — Vite 8 + React 19.2 + TypeScript, see Decision D-10 for the version rationale.

```
frontend/
├── apps/
│   ├── dashboard/        shop owner admin panel — :5173
│   ├── kiosk/            in-store try-on app — :5174
│   ├── portal/           customer web portal — :5175
│   └── mission-control/  super admin panel — :5176
├── packages/
│   ├── ui/     design tokens (colors/typography/spacing/radii/shadows), the Tailwind
│   │           preset built from them, and every shared component (Button, Input,
│   │           Select, Checkbox, Radio, Textarea, Modal, Drawer, Tooltip, Popover,
│   │           Tabs, DataTable, Toast), plus the uiStore/toastStore Zustand stores
│   ├── api/    Axios client factory (tenant header + auth interceptor + single-
│   │           flight 401 refresh), TanStack QueryClient factory, authStore/
│   │           tenantStore
│   ├── tryon/  reserved for Phase 6 — MediaPipe + garment rendering engine
│   └── i18n/   react-i18next instance factory, bn/en `common` namespace resources
├── docker/nginx/default.conf   (referenced by the root docker-compose.yml)
├── package.json                npm workspaces root — see §3.9 for exact commands
├── eslint.config.js             flat config: typescript-eslint + react-hooks +
│                                 react-refresh + simple-import-sort + Prettier
├── .prettierrc.json
└── tsconfig.base.json           strict TS, extended by every app's tsconfig.app.json
```

Each app follows the same internal layout:

```
apps/<app>/src/
├── components/         app-specific components (ErrorFallback.tsx — Sentry.ErrorBoundary
│                       fallback UI — lands here from the very first commit)
├── lib/apiClient.ts    createApiClient(...) instantiated with the app's own VITE_API_URL
├── lib/sentry.ts        initSentry() — no-ops unless VITE_SENTRY_DSN is set
├── pages/               route-level components
├── routes/index.tsx     <AppRoutes /> — the app's <Routes> tree (react-router-dom v7,
│                        declarative mode — BrowserRouter/Routes/Route, API-
│                        compatible with v6 usage)
├── stores/               zustand stores (mission-control only, so far — see below)
├── test/setup.ts         Vitest setup — imports @testing-library/jest-dom/vitest
├── App.tsx               wires I18nextProvider → QueryClientProvider → BrowserRouter
│                         → AppRoutes + <Toaster />
├── main.tsx              initSentry() → createRoot → <Sentry.ErrorBoundary><App /></...>
├── index.css             @tailwind directives; each app's tailwind.config.ts
│                         extends @fitmirror/ui/tailwind-preset
├── vite-env.d.ts         ImportMetaEnv typing for this app's VITE_* variables
└── vitest.config.ts      jsdom environment; optimizeDeps.exclude + server.deps.inline
                          for every @fitmirror/* workspace package — see PROGRESS.md D-11
```

`packages/ui` now also exports `FileUploader`, `EmptyState`, `Skeleton`/`SkeletonCard`/
`SkeletonTableRows`, `ErrorState`, the Recharts wrapper (`TrendLineChart`/
`TrendAreaChart`/`ComparisonBarChart`, `src/components/Chart.tsx`), and
`src/layouts/` (`AppShell`, `KioskShell`, `PortalShell`, `MissionShell`) — every app
wires Sentry + `Sentry.ErrorBoundary` in `main.tsx`, and every app has a working
Vitest suite (`npm run test --workspaces`) plus a shared root `playwright.config.ts`
covering all four apps (`npm run e2e`). Mission Control is the only app with a
`stores/` directory so far (`missionAuthStore.ts`, Phase 1.C) — the other three gain
one once Phase 2.D's tenant-side auth lands. `features/`, `hooks/`, and `layouts/`
(app-local, not to be confused with `packages/ui/src/layouts`) are created per app as
each of those actually lands — not scaffolded speculatively ahead of the code that
needs them.

## 4.4 Backend Naming & Layering Conventions

### 4.4.1 Database Naming

| Object | Convention | Example |
|---|---|---|
| Table | `snake_case`, plural | `tryon_sessions`, `campaign_recipients` |
| Pivot table | singular model names, alphabetical, `snake_case` | `product_occasion`, `taggables` (Laravel morph convention) |
| Primary key | `id` (unsigned big integer, auto-increment) | — |
| Foreign key | `{singular_table}_id` | `tenant_id`, `store_id`, `product_id` |
| Polymorphic pair | `{name}able_id`, `{name}able_type` | `taggable_id` / `taggable_type` |
| Boolean column | `is_`/`has_` prefix | `is_tryon_ready`, `has_variants` |
| Timestamp column | `_at` suffix | `starts_at`, `expires_at`, `paid_at` |
| JSON column | plain noun, no type suffix | `settings`, `audience_filter`, `anchor_points` |
| Enum-like status | dedicated PHP enum class + `string` column, never a MySQL `ENUM` | `TenantStatus`, `SubscriptionStatus`, `CampaignStatus` |
| Index name | Laravel's default (`{table}_{column}_index`) unless a composite index needs a descriptive name | — |

MySQL `ENUM` columns are avoided deliberately — altering a MySQL enum's value set requires a table rewrite, while a PHP backed enum + `string` column can gain new cases in a normal migration. Every status/type/enum column in the schema documented in §6 pairs with a PHP enum class under `app/Enums/`.

Every tenant-owned table carries `tenant_id` (see the `BelongsToTenant` trait, Phase 2.A) — this is the single discriminator that makes single-database multi-tenancy safe, and it is never omitted even when a table also scopes to `store_id`.

### 4.4.2 Service & Query-Builder Conventions (Decision D-09)

FitMirror does not use the Repository pattern. Rationale and full decision text: PROGRESS.md Decision Log, D-09.

- **Controllers** (`app/Http/Controllers/Api/V1/*`, extending `BaseApiController`) stay thin: validate via a `BaseFormRequest`, delegate to a service or a model scope, shape the response via `ApiResponse`. A controller method should read as a short list of steps, not contain business logic itself.
- **Services** (`app/Services/{Module}/*Service.php`, extending `BaseService`) hold business logic that spans multiple models, needs a DB transaction, or orchestrates side effects (events, jobs, notifications). `BaseService::transaction()` wraps `DB::transaction()` so every service commits/rolls back the same way.
- **Models** carry reusable query logic as local scopes (`Product::published()`, `Tenant::active()`, `Subscription::expiringWithin(7)`) rather than a parallel repository class. A scope is discoverable via the model, composes naturally with other query builder calls, and needs no interface.
- **When a query is used in exactly one place**, it stays inline in the service or controller — a scope or repository method is only worth creating once a second caller needs the same query.

### 4.4.3 PHP/Laravel Style

Enforced automatically by Pint (`backend/pint.json`, Laravel preset) and Larastan (`backend/phpstan.neon`, level 6) in CI — see §3.12 and §9 for the exact commands. Notable project-specific rules on top of the Laravel preset: single quotes for strings without interpolation, alphabetically sorted imports, trailing commas in multiline arrays/arguments.

### 4.4.4 Multi-Tenancy Strategy (Phase 2.A)

**Single database, `tenant_id` discriminator, row-level isolation.** FitMirror does not use one database (or schema) per tenant. Every tenant-owned table carries a `tenant_id` foreign key, and isolation is enforced in the application layer, not by infrastructure. This was the assumed strategy since Phase 1 (D-02's rationale for native JSON columns already refers to `tenants.settings`) — Phase 2.A is where it actually gets built and, critically, gets tested for leakage.

**Why not database-per-tenant:** at FitMirror's target scale (§2.4 — up to a few hundred tenants at launch), per-tenant databases multiply migration/backup/connection-pool operational cost for isolation guarantees that row-level scoping provides just as reliably when enforced consistently. A shared database also makes cross-tenant platform features (Mission Control's aggregate revenue dashboard, Phase 10 analytics) simple queries instead of fan-out across N connections.

**How isolation is actually enforced** — three layers, all in `app/`:

1. **`App\Models\Concerns\BelongsToTenant`** — a trait every tenant-owned model uses. It attaches `App\Scopes\TenantScope` as a global scope and auto-fills `tenant_id` on create from the active `TenantContext`. Application code never sets `tenant_id` by hand and can never assign the wrong one.
2. **`App\Scopes\TenantScope`** — **fails closed**. With no active tenant context, a scoped model's query returns zero rows, not every tenant's rows. This is a deliberate reversal of the more common "no context = unscoped" default: forgetting to establish tenant context should surface as an empty result set to debug, never a silent full-table leak. The one way to intentionally read across tenants is explicit: `Model::withoutTenantScope()` for one query, or `TenantContext::runAs($tenant, $callback)` per tenant for a scheduled job/console command that needs to iterate every tenant.
3. **`App\Support\TenantContext`** — a container singleton holding "which tenant is this request/job acting as". `App\Http\Middleware\ResolveTenant` sets it once per request (subdomain → custom domain → `X-Tenant` header → authenticated user's `tenant_id`, in that priority order — see §7.1). `App\Jobs\TenantAwareJob` sets and unconditionally `forget()`s it around every queued job's `handle()`, since `php artisan queue:work` reuses one process (and one container) across many jobs — a job that didn't clean up after itself would leak its tenant into whatever runs next in that worker.

**Proven, not just asserted:** `tests/Feature/Tenancy/TenantScopeIsolationTest.php` exercises this against a throwaway fixture model (`tests/Fixtures/Widget.php` — no real tenant-owned business table exists yet; the first one lands with Phase 2.B's `users` table) and asserts, concretely: tenant A can never see tenant B's rows, `find()` can't reach across the boundary, and no context means no rows. `tests/Feature/Tenancy/ResolveTenantMiddlewareTest.php` covers the middleware's resolution order and the suspended/expired/pending rejection paths end-to-end over real HTTP requests.

**What's still ahead:** tenant provisioning (create tenant + owner user + default roles + default store + default settings in one transaction) and teardown/soft-delete depend on pieces that don't exist yet — `users.tenant_id` (Phase 2.B), roles (Phase 2.C, `spatie/laravel-permission`), and `stores` (Phase 4.A). Building a "provisioning service" now would mean faking those dependencies; PROGRESS.md leaves both tasks unchecked rather than shipping a partial version, and they land alongside Phase 2.B's registration endpoint instead, which is the first real caller.

### 4.4.5 RBAC & Permission Matrix (Phase 2.C)

**Permission naming:** `{module}.{action}`, declared once in `config/permissions.php` under `modules` (13 today: `dashboard`, `tenant_settings`, `staff`, `stores`, `products`, `categories`, `campaigns`, `loyalty`, `customers`, `reports`, `billing`, `kiosk`, `audit_log`) — 40 permission rows total. A module's *table* doesn't need to exist yet for its permissions to be seeded; a controller built in a later phase only has to reference the string.

**Roles are global, not per-tenant.** `RolePermissionSeeder` creates exactly three spatie/laravel-permission roles — `owner` (`*`, every permission), `manager` (everything except billing/tenant-settings/staff-deletion), `staff` (read-only) — on the `web` guard, shared by name across every tenant. A "Manager" in Tenant A and a "Manager" in Tenant B are the *same* `roles` table row; what differs is which `User` rows (each already `TenantScope`-isolated) hold it. spatie's "teams" feature (per-tenant role rows, keyed by `team_id`) is deliberately not enabled — see PROGRESS.md Decision D-14 for why, and when that changes (Max-plan custom roles, still blocked on Phase 3.A's `plans` table).

**Mission Control does not use spatie at all.** `SuperAdmin` has its own fixed three-value `SuperAdminRole` enum (`super_admin`/`support`/`finance`) with a hardcoded `permissions()` map, built in Phase 1.C and left as-is — introducing a second, database-backed role system for three values that will never grow would be two sources of truth for the same facts. See Decision D-15.

**Enforcement is two layers, always both:**
1. **Policy** (`App\Policies\TenantPolicy`, `UserPolicy`, `ActivityPolicy`) — auto-discovered by Laravel's `Model` → `{Model}Policy` convention, called via `$this->authorize(...)` in every gated controller action. Answers "is this user even allowed to do X" (`$user->can('staff.invite')`) plus checks a spatie permission can't express — self-action, tenant-owner immutability.
2. **`TenantScope`** — answers "which rows can they see at all", independent of role. A Manager's `staff.delete` permission is real, but `User::query()` can still only ever resolve to their own tenant's rows in the first place.

**`GET /auth/me`** (§7.3) is the single source of truth the frontend reads roles/permissions from — `useDashboardAuthStore` persists `roles`/`permissions` from that response, and `usePermissions()`/`<Can>` (`apps/dashboard/src/hooks/usePermissions.tsx`) check against it client-side. This governs *rendering* only; the two backend layers above are the actual security boundary regardless of what the UI shows or hides.

---

# 5. Environment Variables Reference

_Every variable is added to this table as it is introduced. Values shown are examples, never real credentials._

## 5.1 Backend — Application

| Variable | Description | Example | Required |
|---|---|---|---|
| `APP_NAME` | Application name used in emails and UI | `FitMirror` | Yes |
| `APP_ENV` | Environment | `local` / `staging` / `production` | Yes |
| `APP_KEY` | Encryption key, generated by `php artisan key:generate` | `base64:...` | Yes |
| `APP_DEBUG` | Debug mode — must be `false` in production | `true` | Yes |
| `APP_URL` | Backend base URL | `http://localhost:8000` | Yes |
| `APP_TIMEZONE` | Server timezone | `Asia/Dhaka` | Yes |
| `APP_LOCALE` | Default locale | `bn` | Yes |
| `APP_FALLBACK_LOCALE` | Fallback locale | `en` | Yes |
| `TENANT_DEFAULT_TIMEZONE` | Presentation-layer display timezone (storage stays UTC per Decision D-07) | `Asia/Dhaka` | Yes |
| `FRONTEND_URL` | Shop owner dashboard URL, used in emails | `http://localhost:5173` | Yes |
| `KIOSK_URL` | Kiosk app URL | `http://localhost:5174` | Yes |
| `PORTAL_URL` | Customer portal URL | `http://localhost:5175` | Yes |
| `MISSION_URL` | Mission Control URL | `http://localhost:5176` | Yes |
| `TENANT_ROOT_DOMAIN` | Root domain for tenant subdomains | `fitmirror.com` | Yes |

## 5.2 Backend — Database

| Variable | Description | Example | Required |
|---|---|---|---|
| `DB_CONNECTION` | Driver | `mysql` | Yes |
| `DB_HOST` | Host | `127.0.0.1` | Yes |
| `DB_PORT` | Port — **3307 locally**, not 3306 (see §2.4.1) | `3307` | Yes |
| `DB_DATABASE` | Database name | `fitmirror` | Yes |
| `DB_USERNAME` | Application DB user — never `root` | `fitmirror` | Yes |
| `DB_PASSWORD` | Password for the application DB user | `FitM1rror#Dev2026` | Yes |
| `DB_CHARSET` | Connection charset | `utf8mb4` | Yes |
| `DB_COLLATION` | Connection collation | `utf8mb4_unicode_ci` | Yes |

## 5.3 Backend — Redis, Cache, Queue, Session

| Variable | Description | Example | Required |
|---|---|---|---|
| `REDIS_HOST` | Redis host | `127.0.0.1` | Yes |
| `REDIS_PASSWORD` | Redis password | `null` | No |
| `REDIS_PORT` | Redis port | `6379` | Yes |
| `CACHE_STORE` | Cache driver | `redis` | Yes |
| `QUEUE_CONNECTION` | Queue driver | `redis` | Yes |
| `SESSION_DRIVER` | Session driver | `redis` | Yes |
| `SESSION_CONNECTION` | Named Redis connection for sessions — its own logical DB, see `config/database.php` | `session` | Yes |
| `SESSION_LIFETIME` | Session lifetime in minutes | `120` | Yes |
| `REDIS_QUEUE_CONNECTION` | Named Redis connection for queue jobs — its own logical DB, separate from cache/session | `queue` | Yes |
| `HORIZON_PREFIX` | Horizon Redis key prefix | `fitmirror_horizon:` | No |

## 5.4 Backend — Search, Storage, Mail, Realtime

| Variable | Description | Example | Required |
|---|---|---|---|
| `SCOUT_DRIVER` | Search driver | `meilisearch` | Yes |
| `MEILISEARCH_HOST` | MeiliSearch URL | `http://127.0.0.1:7700` | Yes |
| `MEILISEARCH_KEY` | MeiliSearch master key — local dev value: `tyb3p1ksdm6r5a8wzig7vule2nfx9j40ochq` | `tyb3p1ksdm6r5a8wzig7vule2nfx9j40ochq` | Yes |
| `FILESYSTEM_DISK` | Default disk | `s3` | Yes |
| `TENANT_DISK_DRIVER` | Overrides the `tenant` disk driver directly; blank follows `FILESYSTEM_DISK` (local dev → `local`, production → `s3`) | (blank) | No |
| `AWS_ACCESS_KEY_ID` | S3/R2 access key | `AKIA...` | Yes |
| `AWS_SECRET_ACCESS_KEY` | S3/R2 secret | `...` | Yes |
| `AWS_DEFAULT_REGION` | Region | `ap-southeast-1` | Yes |
| `AWS_BUCKET` | Bucket name | `fitmirror-media` | Yes |
| `AWS_ENDPOINT` | Custom endpoint (Cloudflare R2) | `https://<id>.r2.cloudflarestorage.com` | No |
| `AWS_URL` | CDN base URL for public assets | `https://cdn.fitmirror.com` | No |
| `MAIL_MAILER` | Mail transport | `smtp` / `ses` | Yes |
| `MAIL_HOST` | SMTP host (dev) | `sandbox.smtp.mailtrap.io` | No |
| `MAIL_PORT` | SMTP port | `2525` | No |
| `MAIL_USERNAME` | SMTP user | `...` | No |
| `MAIL_PASSWORD` | SMTP password | `...` | No |
| `MAIL_FROM_ADDRESS` | Sender address | `no-reply@fitmirror.com` | Yes |
| `MAIL_FROM_NAME` | Sender name | `FitMirror` | Yes |
| `REVERB_APP_ID` | Reverb app id | `fitmirror` | Yes |
| `REVERB_APP_KEY` | Reverb key | `...` | Yes |
| `REVERB_APP_SECRET` | Reverb secret | `...` | Yes |
| `REVERB_HOST` | Reverb host | `127.0.0.1` | Yes |
| `REVERB_PORT` | Reverb port | `8080` | Yes |

## 5.5 Backend — Payments, SMS, Integrations, Monitoring

| Variable | Description | Example | Required |
|---|---|---|---|
| `SSLC_STORE_ID` | SSLCommerz store ID | `fitmir65abc...` | Yes |
| `SSLC_STORE_PASSWORD` | SSLCommerz store password | `fitmir65abc@ssl` | Yes |
| `SSLC_SANDBOX` | Use sandbox gateway | `true` | Yes |
| `SSLC_SUCCESS_URL` | Success callback — see §7.5 `/payment/callback/success` | `${APP_URL}/api/v1/payment/callback/success` | Yes |
| `SSLC_FAIL_URL` | Failure callback | `${APP_URL}/api/v1/payment/callback/fail` | Yes |
| `SSLC_CANCEL_URL` | Cancel callback | `${APP_URL}/api/v1/payment/callback/cancel` | Yes |
| `SSLC_IPN_URL` | IPN webhook | `${APP_URL}/api/v1/payment/ipn` | Yes |
| `SSLC_REFUND_INITIATE_ENDPOINT` | SSLCommerz refund-initiate URL — deliberately blank by default, not hardcoded (Decision D-18) | (blank until confirmed) | No — refunds fail fast with a clear error until set |
| `SSLC_REFUND_QUERY_ENDPOINT` | SSLCommerz refund-status-query URL — same reasoning as above | (blank until confirmed) | No |
| `VAT_RATE` | VAT applied to the post-discount subtotal of every plan/add-on invoice (Phase 3.D) | `0.15` | Yes |
| `SMS_DRIVER` | SMS gateway driver | `bulksmsbd` | Yes |
| `SMS_API_KEY` | Gateway API key | `...` | Yes |
| `SMS_SENDER_ID` | Approved sender ID | `FitMirror` | Yes |
| `FACEBOOK_APP_ID` | Facebook app ID for catalog sync | `...` | No |
| `FACEBOOK_APP_SECRET` | Facebook app secret | `...` | No |
| `WHATSAPP_PHONE_ID` | WhatsApp Business phone number ID | `...` | No |
| `WHATSAPP_TOKEN` | WhatsApp permanent token | `...` | No |
| `GOOGLE_CLIENT_ID` | Google OAuth client (My Business) | `...` | No |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret | `...` | No |
| `FCM_SERVER_KEY` | Firebase Cloud Messaging key | `...` | No |
| `SENTRY_LARAVEL_DSN` | Sentry DSN | `https://...ingest.sentry.io/...` | Production |
| `SENTRY_ENVIRONMENT` | Overrides the Sentry environment tag; blank falls back to `APP_ENV` | (blank) | No |
| `SENTRY_RELEASE` | Release tag, set in CI to the deployed commit SHA | `$(git rev-parse HEAD)` | No |
| `SENTRY_TRACES_SAMPLE_RATE` | Performance trace sampling (0.0–1.0) | `0.2` | Production |
| `SENTRY_PROFILES_SAMPLE_RATE` | Profiling sampling (0.0–1.0) | `0.0` | No |
| `TELESCOPE_ENABLED` | Enable Telescope — only honoured in local/staging; `AppServiceProvider` never registers it in production regardless of this value | `true` | Yes |
| `BG_REMOVAL_ENDPOINT` | Background removal service URL | `http://127.0.0.1:5000/remove` | Yes |
| `BG_REMOVAL_KEY` | Background removal API key | `...` | No |
| `SUPER_ADMIN_NAME` | Seeded super admin display name | `Product Owner` | Yes |
| `SUPER_ADMIN_EMAIL` | Seeded super admin email | `owner@fitmirror.com` | Yes |
| `SUPER_ADMIN_PASSWORD` | Seeded super admin password. If left blank on the *first* seed run, `SuperAdminSeeder` generates one and prints it once — see §8.12. Re-running the seeder never overwrites an existing hash unless this is set. | `...` | Yes |

## 5.6 Frontend

Each app has its own `.env.example` under `frontend/apps/<app>/` (not one shared file at the workspace root — see §3.9). Read via `import.meta.env.*`, typed in each app's `src/vite-env.d.ts`.

| Variable | Description | Example | Required | Apps |
|---|---|---|---|---|
| `VITE_API_URL` | Tenant API base | `http://localhost:8000/api/v1` | Yes | dashboard, kiosk, portal |
| `VITE_MISSION_API_URL` | Mission Control API base | `http://localhost:8000/api/v1/mission` | Yes | mission-control only |
| `VITE_REVERB_APP_KEY` | Reverb app key | `...` | No (Phase 9 chat) | dashboard, kiosk, portal |
| `VITE_REVERB_HOST` | Reverb host | `127.0.0.1` | No | dashboard, kiosk, portal |
| `VITE_REVERB_PORT` | Reverb port | `8080` | No | dashboard, kiosk, portal |
| `VITE_REVERB_SCHEME` | `http` locally, `https` in production | `http` | No | dashboard, kiosk, portal |
| `VITE_SENTRY_DSN` | Frontend Sentry DSN | `https://...` | Production | all four |
| `VITE_CDN_URL` | Media CDN base | `https://cdn.fitmirror.com` | No | dashboard, kiosk, portal |

---

# 6. Database Schema

_To be filled as we build migrations. Each table is documented here immediately after its migration is written._

**Documentation format used for every table:**

### `table_name`
Purpose: one line.

| Column | Type | Null | Default | Description |
|---|---|---|---|---|
| | | | | |

Indexes: …
Relationships: …

## 6.1 Planned Table Groups

| Group | Tables |
|---|---|
| Tenancy & Auth | `tenants`, `users`, `super_admins`, `roles`, `permissions`, `model_has_roles`, `login_attempts`, `personal_access_tokens`, `activity_log` |
| Plans & Billing | `plans`, `plan_limits`, `plan_features`, `feature_flags`, `subscriptions`, `invoices`, `invoice_items`, `invoice_number_sequences`, `payments`, `refunds`, `coupons`, `coupon_redemptions`, `addons`, `tenant_addons` |
| Stores & Staff | `stores`, `store_hours`, `kiosk_devices`, `shifts`, `franchise_groups` |
| Catalog | `categories`, `attributes`, `attribute_values`, `occasions`, `tags`, `taggables`, `products`, `product_variants`, `product_images`, `product_attribute`, `product_occasion`, `size_charts`, `price_history`, `stock_movements`, `catalog_links` |
| Try-On | `tryon_sessions`, `tryon_events`, `garment_assets`, `size_estimations`, `fit_feedback` |
| Campaigns | `campaigns`, `campaign_products`, `campaign_variants`, `campaign_recipients`, `campaign_metrics`, `campaign_templates`, `link_clicks`, `referrals` |
| Loyalty | `loyalty_programs`, `loyalty_tiers`, `loyalty_point_rules`, `loyalty_transactions`, `customer_loyalty` |
| Customers | `customers`, `customer_addresses`, `wishlists`, `wishlist_items`, `reviews`, `appointments`, `chat_conversations`, `chat_messages`, `notification_preferences` |
| Analytics | `analytics_daily_store`, `analytics_hourly_store`, `analytics_daily_product`, `analytics_daily_category`, `analytics_daily_customer` |
| Notifications | `notifications`, `notification_templates`, `sms_logs`, `push_devices` |
| Integrations | `integrations`, `api_keys`, `webhook_endpoints`, `webhook_deliveries` |
| Mission Control | `announcements`, `support_tickets`, `support_ticket_messages` |

## 6.2 Entity Relationship Diagram

_Exported to `docs/erd.png` and embedded here once the schema stabilises at the end of Phase 5._

## 6.3 Table Documentation

### `tenants`
Purpose: one row per shop owner account — the root of every tenant-scoped query. See §4.4.4 for the isolation strategy.

| Column | Type | Null | Default | Description |
|---|---|---|---|---|
| `id` | bigint unsigned | No | — | Primary key — this is the `tenant_id` every other tenant-owned table references |
| `name` | string | No | — | Shop/business name |
| `slug` | string | No | — | Unique, URL-safe identifier — also the subdomain and the `X-Tenant` header value in dev |
| `subdomain` | string | No | — | Currently always equal to `slug`; kept as its own column since a future custom-subdomain feature could let it diverge |
| `custom_domain` | string | Yes | null | Max-plan custom domain (Phase 4.A), checked before subdomain in `ResolveTenant` |
| `owner_id` | bigint unsigned | Yes | null | FK → `users.id`, `nullOnDelete`. Nullable because provisioning creates the tenant before the owner user exists — backfilled once the owner is created |
| `status` | string | No | `pending` | Backed by `App\Enums\TenantStatus` — `pending`, `trial`, `active`, `suspended`, `expired`, `rejected` |
| `trial_ends_at` | timestamp | Yes | null | Set when a trial starts (Phase 3.B) |
| `plan_id` | bigint unsigned | Yes | null | **No FK constraint yet** — `plans` table doesn't exist until Phase 3.A. Real, usable column; the constraint is added by a Phase 3.A migration once the referenced table exists |
| `settings` | json | Yes | null | Tenant-level preferences (kiosk idle timeout, branding, etc.), read via `Tenant::setting('key.path', $default)` |
| `created_at` / `updated_at` | timestamp | Yes | null | Standard timestamps |
| `deleted_at` | timestamp | Yes | null | Soft delete — see §4.4.4 "teardown" note |

Indexes: unique on `slug`, `subdomain`, `custom_domain`.
Relationships: `belongsTo(User::class, 'owner_id')` as `owner()`. Every other tenant-owned model relates back via the `BelongsToTenant` trait's `tenant()` relation, not the other way around — `Tenant` itself has no `hasMany` declared per business table, since Phase 2.A ships before any of those tables exist.

### `super_admins`
Purpose: Mission Control (Product Owner / platform-operator) accounts. Deliberately not a row in `users` — see §8.12 and PROGRESS.md Phase 1.C.

| Column | Type | Null | Default | Description |
|---|---|---|---|---|
| `id` | bigint unsigned | No | — | Primary key |
| `name` | string | No | — | Display name |
| `email` | string | No | — | Unique login identifier |
| `password` | string | No | — | Bcrypt hash |
| `two_factor_secret` | text | Yes | null | TOTP secret, encrypted at rest via the model's `encrypted` cast |
| `two_factor_recovery_codes` | text | Yes | null | JSON array of recovery codes, encrypted at rest via `encrypted:array` |
| `two_factor_confirmed_at` | timestamp | Yes | null | Set once the TOTP setup flow is confirmed (2FA setup flow itself ships in a later phase) |
| `role` | string | No | `super_admin` | Backed by `App\Enums\SuperAdminRole` — `super_admin`, `support`, `finance` |
| `status` | string | No | `active` | Backed by `App\Enums\SuperAdminStatus` — `active`, `suspended` |
| `last_login_at` | timestamp | Yes | null | Updated on every successful `POST /api/v1/mission/login` |
| `remember_token` | string | Yes | null | Laravel standard |
| `created_at` / `updated_at` | timestamp | Yes | null | Standard timestamps |

Indexes: unique on `email`.
Relationships: `morphMany` to `personal_access_tokens` via Sanctum's `HasApiTokens` (same table tenant users' tokens live in — `tokenable_type` discriminates the two).

### `users`
Purpose: tenant-side accounts — shop owners and (from Phase 2.C) staff. The default Laravel scaffold table, extended by a Phase 2.B migration rather than rewritten. Uses `BelongsToTenant` — see §4.4.4.

| Column | Type | Null | Default | Description |
|---|---|---|---|---|
| `id` | bigint unsigned | No | — | Primary key |
| `tenant_id` | bigint unsigned | Yes | null | FK → `tenants.id`, `cascadeOnDelete`. Nullable only because a factory/edge case can construct a user before its tenant exists; every real registered user has one |
| `store_id` | bigint unsigned | Yes | null | **No FK constraint yet** — `stores` doesn't exist until Phase 4.A. Same pattern as `tenants.plan_id` |
| `name` | string | No | — | Display name |
| `email` | string | No | — | **Globally unique**, not per-tenant — login resolves a user by email alone before any tenant is known, which a per-tenant-unique email would make ambiguous. See PROGRESS.md Phase 2.B migration comment |
| `email_verified_at` | timestamp | Yes | null | Set by `GET /api/v1/auth/email/verify/{id}/{hash}` |
| `phone` | string | Yes | null | |
| `password` | string | No | — | Bcrypt hash |
| `avatar` | string | Yes | null | Path on the `public` disk; `PATCH /api/v1/auth/profile` deletes the old file when replaced |
| `locale` | string(5) | No | `bn` | `bn` or `en` |
| `status` | string | No | `active` | Backed by `App\Enums\UserStatus` — `invited`, `active`, `suspended` |
| `last_login_at` | timestamp | Yes | null | Updated on every successful login (both the password step and, if 2FA is enabled, the completed challenge) |
| `two_factor_secret` / `two_factor_recovery_codes` / `two_factor_confirmed_at` | text / text / timestamp | Yes | null | Same shape as `super_admins`, encrypted at rest the same way |
| `remember_token`, `created_at` / `updated_at`, `deleted_at` | — | Yes | null | Laravel standard + soft delete |

Indexes: unique on `email`.
Relationships: `belongsTo(Tenant::class)` as `tenant()` (via `BelongsToTenant`). `Tenant::owner()` points back at the specific `User` who owns the tenant — see `User::isTenantOwner()`.

### `login_attempts`
Purpose: append-only audit trail backing progressive account lockout (`App\Services\Auth\LoginService`). Keyed on the submitted email, not a `user_id` FK, so a guess against a nonexistent email is still recorded and a deleted user's history isn't lost.

| Column | Type | Null | Default | Description |
|---|---|---|---|---|
| `id` | bigint unsigned | No | — | Primary key |
| `email` | string | No | — | The email submitted, whether or not it matched a real account |
| `ip_address` | string(45) | No | — | IPv4 or IPv6 |
| `user_agent` | text | Yes | null | |
| `successful` | boolean | No | — | |
| `created_at` | timestamp | Yes | null | No `updated_at` — rows are never modified |

Indexes: composite on `(email, created_at)`, matching `LoginAttempt::consecutiveFailures()`'s query shape.

---

# 7. API Reference

_To be filled as we build. Every endpoint is documented the moment its controller is written._

## 7.1 Conventions

**Base URLs**

| Surface | Base URL |
|---|---|
| Tenant API | `{APP_URL}/api/v1` |
| Mission Control API | `{APP_URL}/api/v1/mission` |
| Public REST API (Max plan) | `{APP_URL}/api/public/v1` |

**Authentication**
Bearer tokens issued by Laravel Sanctum:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
Accept-Language: bn
```

Kiosk devices authenticate with a long-lived device token. Public API consumers use `X-API-Key`.

**Success envelope**

```json
{
  "success": true,
  "message": "Product created successfully.",
  "data": { }
}
```

**Paginated envelope**

```json
{
  "success": true,
  "data": [ ],
  "meta": { "current_page": 1, "per_page": 20, "total": 137, "last_page": 7 }
}
```

**Error envelope**

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email has already been taken."] }
}
```

**Status codes**

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 204 | No content |
| 401 | Unauthenticated |
| 402 | Payment required / subscription inactive |
| 403 | Forbidden (permission or plan feature) |
| 404 | Not found |
| 409 | Conflict (state transition not allowed) |
| 422 | Validation failed |
| 423 | Locked (account lockout / maintenance) |
| 429 | Rate limit exceeded |
| 500 | Server error |

**Endpoint documentation format used below:**

### `METHOD /path`
Purpose. **Auth:** required role/permission. **Plan:** required feature.

Request body:
```json
{ }
```
Response `200`:
```json
{ }
```

## 7.2 System Endpoints

### `GET /api/v1/health`
Unauthenticated liveness/readiness probe. Checks the database connection, the default Redis connection, and the `queue` Redis connection independently, so a degraded dependency is visible instead of a generic crash. Used by uptime monitors, load balancer health checks, and `DOCUMENTATION.md` §3.11 local verification.

**Auth:** none. **Plan:** none.

Response `200` (healthy):
```json
{
  "success": true,
  "message": "FitMirror API is healthy.",
  "data": {
    "status": "ok",
    "checks": { "app": true, "database": true, "redis": true, "queue": true },
    "timestamp": "2026-08-01T04:14:02+00:00"
  }
}
```

Response `503` (degraded) — same shape, `"status": "degraded"`, and at least one check is `false`.

## 7.3 Authentication Endpoints

All mounted under `/api/v1/auth`. Tenant-facing only — never confuse with Mission Control's separate `/api/v1/mission/login` etc. (§7.15), which authenticates a totally different model against a totally different guard.

### `POST /auth/register`
Shop-owner registration. Creates a `pending` tenant and its owner user in one transaction (`App\Services\Auth\RegistrationService`), fires `TenantRegistered`, and emails a verification link. Does **not** log the new owner in — no token is issued.

**Auth:** none. **Throttle:** `auth` (5/min per IP+email).

Request: `{ "tenant_name": "...", "name": "...", "email": "...", "phone": "...", "password": "...", "password_confirmation": "..." }`
Response `201`: `{ "data": { "tenant": { "id", "name", "slug", "status": "pending" }, "user": { "id", "name", "email" } } }`

### `POST /auth/login`
Two possible `data.status` values the client branches on:
- `"authenticated"` — `data.token` is a real Sanctum token, issued with the `['*']` ability (fine-grained abilities are a Phase 2.C RBAC concern, not yet enforced).
- `"two_factor_required"` — `data.two_factor_token` must be submitted to `/auth/2fa/challenge` before any real token is issued.

Rejects with `422` and a generic "credentials do not match" message for both an unknown email and a wrong password (no user enumeration), and separately once **5 consecutive failures** since the account's last success have accumulated (`App\Models\LoginAttempt::consecutiveFailures()`) — a **progressive** lockout independent of the per-minute `throttle:auth` limiter: `5 × (failures − 4)` minutes.

**Auth:** none. **Throttle:** `auth`.

Request: `{ "email": "...", "password": "..." }`

### `POST /auth/2fa/challenge`
Step two of login for a 2FA-enabled account. Accepts either a live TOTP `code` or a one-time `recovery_code`. The `two_factor_token` from the login response expires after 5 minutes (server-side cache entry).

**Auth:** none (the `two_factor_token` itself is the credential). **Throttle:** `auth`.

Request: `{ "two_factor_token": "...", "code": "123456" }` or `{ "two_factor_token": "...", "recovery_code": "abcd-1234" }`

### `GET /auth/email/verify/{id}/{hash}`
Named route `verification.verify` — hardcoded by `Illuminate\Auth\Notifications\VerifyEmail`; renaming breaks every already-sent email. Signed, 60-minute expiry.

**Auth:** none (the signature is the credential). **Throttle:** `auth`.

### `POST /auth/forgot-password` / `POST /auth/reset-password`
Standard Laravel password-reset broker (`password_reset_tokens` table). `forgot-password` always returns the same generic message regardless of whether the email exists. `reset-password` revokes **every** existing Sanctum token on success — a password reset means the old session is no longer trusted.

**Auth:** none. **Throttle:** `auth`.

### `POST /auth/logout`
Revokes only the token used on this request (`currentAccessToken()->delete()`) — logging out one device never logs out another.

**Auth:** `sanctum`.

### `GET /auth/me`
Returns the authenticated user, their tenant, roles, and permissions (Phase 2.C — `spatie/laravel-permission`). `plan`/`limits` are still honestly `null`/`[]`: `plans` doesn't exist until Phase 3.A.

**Auth:** `sanctum`.

Response `200` (abridged):
```json
{
  "data": {
    "user": { "id": 4, "name": "...", "email": "...", "is_tenant_owner": false, "...": "..." },
    "tenant": { "id": 1, "name": "...", "slug": "...", "status": "active" },
    "plan": null,
    "limits": [],
    "roles": ["manager"],
    "permissions": ["dashboard.view", "staff.view", "staff.invite", "..."]
  }
}
```

### `POST /auth/invitations/accept`
Accepts a staff invitation (§7.3a) — creates the User only now, not at invite time, and immediately logs them in with a fresh Sanctum token.

**Auth:** none (the token is the credential). **Throttle:** `auth`.

Request: `{ "token": "...", "name": "...", "password": "...", "password_confirmation": "..." }`
Response `200`: `{ "data": { "token": "...", "user": {...}, "tenant": {...} } }`

### `POST /auth/impersonation/exit`
Revokes the *current* token and closes the matching `impersonations` audit row (§7.15). `403` (`not_impersonating`) if the current token isn't an impersonation token — checked by token **name**, not ability, since ordinary login tokens carry the `['*']` wildcard ability that would otherwise make an ability-only check a no-op.

**Auth:** `sanctum`.

Response `204`: empty body.

### `POST /auth/change-password`
Requires `current_password`. Revokes every **other** token but keeps the one used for this request alive, so the client isn't logged out by its own successful call.

**Auth:** `sanctum`.

### `PATCH /auth/profile`
Updates `name`/`phone`/`avatar`/`locale`. Notification preferences are excluded — `notification_preferences` doesn't exist until Phase 9.A. Avatar upload replaces (and deletes) any previous file on the `public` disk.

**Auth:** `sanctum`.

### `GET /auth/sessions` / `DELETE /auth/sessions/{tokenId}`
"Sessions" means Sanctum personal access tokens — this is an API-only app with no server-side session store. `is_current` flags whichever token made the request. Revoking a token that doesn't belong to the caller returns `404`, not `403` (never confirms another account's token even exists).

**Auth:** `sanctum`.

### `POST /auth/2fa/enable` / `/confirm` / `/disable` / `/recovery-codes`
`enable` generates and stores an unconfirmed secret, returning `{ secret, otpauth_url, qr_code_svg }`. `confirm` validates a real code, marks 2FA active, and returns 8 recovery codes **in plaintext exactly once** — only their hashes are persisted, the same as passwords. `recovery-codes` regenerates and returns a fresh set (invalidating the old ones).

**Auth:** `sanctum`.

## 7.3a RBAC, Staff & Audit Endpoints (Phase 2.C)
Mounted at `/api/v1/staff` and `/api/v1/audit-log`. All require `auth:sanctum` + `tenant.active` + `tenant.2fa` — the first routes in the app to actually attach those two middleware (defined since Phase 2.B, unattached to any route until now). Every action is gated by a `UserPolicy`/`ActivityPolicy` check on top of the spatie permission itself.

### `GET /staff`
Paginated staff list for the caller's own tenant. `roles` is an array (single-element in practice — one role per user today). Requires `staff.view`.

Response item: `{ "id", "name", "email", "phone", "avatar", "status", "is_owner", "roles": ["manager"], "last_login_at", "created_at" }`

### `GET /staff/{target}`
Requires `staff.view`. `{target}` resolves through normal (tenant-scoped) route binding — a cross-tenant id 404s before the controller runs.

### `PATCH /staff/{target}/role`
Requires `staff.update`. Body: `{ "role": "manager" }` (`manager` or `staff` — `owner` is never assignable here, see `StaffInvitationService::invitableRoles()`). The literal tenant owner (`Tenant::owner_id`) can only be changed by themselves.

### `POST /staff/{target}/deactivate` / `POST /staff/{target}/reactivate`
Requires `staff.deactivate` / `staff.update`. Deactivating revokes every one of that user's Sanctum tokens immediately — a suspended account with a live token would otherwise keep working until that token separately expired.

### `DELETE /staff/{target}`
Requires `staff.delete`. Soft-deletes and revokes all tokens. Neither the caller's own account nor the tenant owner can be deleted this way.

### `GET /staff/invitations` / `POST /staff/invitations` / `DELETE /staff/invitations/{invitation}`
List pending invitations, send a new one, or revoke a pending one. `POST` requires `staff.invite`; body `{ "name": "...", "email": "...", "role": "manager" }`. Sends `App\Notifications\StaffInvitationNotification` to a signed-looking-but-not-Laravel-signed link (`{FRONTEND_URL}/invite/accept?token=...`) — a sha256-hashed random token stored in `staff_invitations`, not `URL::signedRoute()`, because the accept flow is a frontend SPA route the backend never directly parses. Accepting is `POST /auth/invitations/accept` (§7.3), unauthenticated by design.

### `GET /audit-log`
Filtered, paginated activity feed. Query params: `user_id`, `module` (`tenant`/`user`/`impersonation`), `action` (`created`/`updated`/`deleted`/`restored`), `date_from`, `date_to`. Requires `audit_log.view`. Backed by `App\Models\Activity` (replaces spatie/laravel-activitylog's own model — adds `tenant_id` via `BelongsToTenant`, so a row from another tenant can never leak through even if the filters were somehow bypassed).

Response item: `{ "id", "module", "action", "description", "causer": { "id", "name", "email" } | null, "subject_type", "subject_id", "changes": { "attributes": {...}, "old": {...} }, "created_at" }`

## 7.4 Tenant & Profile Endpoints
_Reserved for tenant-settings endpoints (branding, business hours, etc.) — not yet built. User profile self-service lives under `/auth/profile` (§7.3) since it's part of the auth surface, not tenant administration._

## 7.5 Subscription & Billing Endpoints (Phase 3.A/3.B/3.C)
Mounted under `/api/v1` alongside the other tenant business routes. Most of this section requires `auth:sanctum` + `tenant.active` + `tenant.2fa`, but the payment endpoints deliberately don't all share that exact group — see each one's own auth line below. Checkout-with-coupon, upgrade/downgrade, and invoice listing/download are still empty — blocked on Phase 3.D (see PROGRESS.md Phase 3.B/3.D's own notes on what's deferred and why).

### `GET /plan/usage`
Current usage vs. the tenant's resolved plan limits (`App\Services\Plan\PlanService::resolve()` — falls back to the Free plan for a tenant with no `plan_id` chosen yet). One row per `plan_limits` key; `current` is `null`, not `0`, for a metric this phase has no live counter for (`categories`/`skus`/`branches`/`storage_gb` — those land with Phase 4/5).

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa`.

Response `200`:
```json
{
  "data": {
    "plan": { "id": 2, "name": "Pro", "slug": "pro" },
    "usage": [
      { "key": "try_on_sessions_per_day", "current": 7, "limit": 500, "unlimited": false },
      { "key": "staff_accounts", "current": 1, "limit": 5, "unlimited": false },
      { "key": "categories", "current": null, "limit": 10, "unlimited": false },
      { "key": "storage_gb", "current": null, "limit": 50, "unlimited": false }
    ]
  }
}
```

### `GET /plans`
Public, unauthenticated — the pricing page's data source (Phase 3.E). `Plan::publicPlans()` (`is_public` + `Active`, ordered by `sort_order`) with `limits`/`features` eager-loaded.

**Auth:** none.

Response `200`: `{ "data": [ { "id", "name", "slug", "price_monthly", "price_yearly", "currency", "trial_days", "limits": {...}, "features": {...} } ] }`

### `GET /subscription`
The caller's current trialing/active/past_due/grace subscription (`SubscriptionService::currentFor()` — the same lookup `/subscription/cancel` and `/subscription/auto-renew` already used internally). `data: null` for a tenant with none yet — a real, expected state, not an error.

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa`.

Response `200`: `{ "data": { "id", "plan_id", "billing_cycle", "status", "auto_renew", "starts_at", "trial_ends_at", "ends_at", "cancelled_at" } | null }`

### `POST /subscription/cancel`
Owner-only (`isTenantOwner()`, not a spatie permission — mirrors why `tenant_settings.update` is owner-exclusive in `config/permissions.php`). `immediately: true` transitions the current subscription to `cancelled` right away; `immediately: false` only sets `auto_renew: false` and records `reason` — the actual end-of-period transition to `cancelled` is Phase 3.B's still-unbuilt auto-renewal job's responsibility (it would see `auto_renew = false` at the subscription's renewal date and simply not renew).

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa`.

Request: `{ "immediately": true, "reason": "Too expensive" }` (`reason` optional).
Response `200`: `{ "data": { "id", "status", "auto_renew", "cancelled_at" } }`
Response `404` (`no_active_subscription`) if the tenant has no trialing/active/past_due/grace subscription. Response `403` if the caller isn't the literal tenant owner.

### `PATCH /subscription/auto-renew` (Phase 3.D)
Owner-only. Toggles `auto_renew` independently of cancellation — e.g. turning it back on after a scheduled (non-immediate) cancel flipped it off — without a full resume/reactivate flow (still SKIPPED, see PROGRESS.md Phase 3.B).

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa`.

Request: `{ "auto_renew": true }`
Response `200`: `{ "data": { "id", "auto_renew" } }`
Response `404` (`no_active_subscription`) if the tenant has no current subscription.

### `POST /payment/initiate`
Owner-only. Starts (or resumes, if a prior attempt already created one) a `PendingPayment` `Subscription` + a `Pending` `Invoice` for the given plan/cycle — with an optional coupon applied and VAT computed (Phase 3.D) — then calls SSLCommerz's session-initiate API and returns the gateway redirect URL. Deliberately **not** behind `tenant.active` — a tenant paying for the first time is, by definition, not yet active (`TenantStatus::Pending`'s own label is "Pending Approval"); gating payment behind an active tenant would make it impossible to ever pay. Still behind `tenant.2fa`, since the owner must already have finished 2FA setup to reach any business route.

**Auth:** `sanctum` + `tenant.2fa` (no `tenant.active`).

Request: `{ "plan_id": 2, "billing_cycle": "monthly", "coupon_code": "SAVE20" }` (`coupon_code` optional).
Response `200`: `{ "data": { "invoice_number", "subtotal", "discount", "vat", "amount", "currency", "gateway_url" } }` — redirect the browser to `gateway_url` to complete payment.
Response `422` if the plan isn't purchasable, the coupon is invalid/expired/exhausted (see `POST /billing/coupon/preview` below for the exact validation rules), or the tenant already has a trialing/active/awaiting-approval subscription (upgrade/downgrade isn't built yet — see Phase 3.B). Response `502` (`payment_gateway_error`) if SSLCommerz credentials are unconfigured or the session-initiate call itself fails.

### `POST /payment/callback/success` / `/fail` / `/cancel`
SSLCommerz posts here directly (an auto-submitting form on the customer's browser, not a FitMirror-authenticated request) after a completed checkout session. Each looks up its `Payment` by the posted `tran_id` — unauthenticated by design, and deliberately not behind `tenant.active`/`tenant.2fa` either, for the same reason as `/payment/initiate` above.

The success callback re-verifies via SSLCommerz's `validationserverAPI` order-validation call (status must be `VALID`/`VALIDATED` *and* the validated amount must match the payment — this is SSLCommerz's server-to-server stand-in for a client-side signature check, since the classic REST integration has no HMAC payload signing). A verified success marks the `Invoice` `Paid` and transitions the `Subscription` `PendingPayment → PendingApproval`. Fail/cancel just record the outcome — never touch the subscription.

All three always redirect (`302`) the browser to `config('app.frontend_url')/billing/payment/{success|failed|cancelled}?invoice={number}` — the dashboard's `PaymentResultPage.tsx` (Phase 3.E) renders that exact route.

**Auth:** none.

### `POST /payment/ipn`
SSLCommerz's server-to-server Instant Payment Notification — fires independently of whether the customer's browser ever reaches the success callback (closed tab, dropped connection mid-redirect). Always responds `200` so SSLCommerz doesn't endlessly retry delivery. Idempotent: replaying the same `tran_id` (a payment already marked `Success`) short-circuits without re-validating or re-transitioning anything, and without calling SSLCommerz's validation API a second time.

**Auth:** none.

### `POST /billing/coupon/preview` (Phase 3.D)
Validates a coupon code against a plan/cycle and returns the computed discount, without writing anything — the checkout page's "apply coupon" action. No "remove" endpoint exists: nothing is persisted until the coupon is actually redeemed against a real invoice (`POST /payment/initiate`'s own `coupon_code` field), so removing it client-side needs no API call. Reachable in the same pre-approval window as `/payment/initiate` (no `tenant.active`), since coupons are applied *during* the first checkout.

**Auth:** `sanctum` + `tenant.2fa` (no `tenant.active`).

Request: `{ "code": "SAVE20", "plan_id": 2, "billing_cycle": "monthly" }`
Response `200`: `{ "data": { "code", "subtotal", "discount", "total_before_vat" } }`
Response `422` (`coupon` field error) if the code doesn't exist, isn't active, is outside its start/expiry window, doesn't apply to the chosen plan, or has hit its global (`max_redemptions`) or per-tenant (`per_tenant_limit`) redemption cap.

### `GET /billing/invoices` / `GET /billing/invoices/{invoice}/download` (Phase 3.D)
Every plan and add-on invoice for the caller's tenant, newest first. `{invoice}` uses normal tenant-scoped route-model binding — a cross-tenant id 404s. `downloadable` in the list response is `false` until `GenerateInvoicePdfJob` finishes (near-instant in practice, since it's dispatched the moment an invoice is marked Paid); the download route itself 404s with `invoice_pdf_not_ready` if hit before that.

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa` + `billing.view` permission.

`GET /billing/invoices` response `200`: `{ "data": [ { "id", "number", "type": "plan"|"addon", "subtotal", "discount", "vat", "total", "currency", "status", "issued_at", "paid_at", "downloadable" } ], "meta": {...} }`
`GET /billing/invoices/{invoice}/download` response `200`: the PDF file (`Content-Disposition: attachment`).

### `GET /billing/history` (Phase 3.D)
Payments and refunds for the caller's tenant, merged into one chronological feed (newest first). No "credits" row type exists — there is no credit-ledger concept anywhere in this codebase.

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa` + `billing.view` permission.

Response `200`: `{ "data": [ { "type": "payment"|"refund", "id", "gateway", "amount", "currency", "status", "created_at" } ], "meta": {...} }`

### `GET /billing/addons` / `POST /billing/addons/{addon}/purchase` (Phase 3.D)
The add-on marketplace catalog, and purchasing one — reuses the exact SSLCommerz pipeline `/payment/initiate` uses (`App\Services\Billing\AddonPurchaseService`, built on the same `GatewayCheckoutService`). A verified add-on payment credits a new `TenantAddon` row (`unit_amount × quantity`), drawn down later by `AddonConsumptionService`.

**Auth:** `sanctum` + `tenant.active` + `tenant.2fa` (list); purchase additionally requires the caller be the tenant owner.

`GET /billing/addons` response `200`: `{ "data": [ { "id", "code", "name", "description", "type", "price", "currency", "unit_amount" } ] }`
`POST /billing/addons/{addon}/purchase` request: `{ "quantity": 2 }` (optional, defaults to 1). Response `200`: `{ "data": { "invoice_number", "amount", "currency", "gateway_url" } }`.

### Plan-gated and limit-gated errors (used across every module, not just this section)
Any plan-feature-gated route (`->middleware('plan.feature:{key}')`) returns `403` with `error_code: "plan_feature_unavailable"` and `errors: { feature, upgrade_url }`. Any limit check (`PlanService::assertWithinLimit()`, e.g. staff invites — §7.3a) throws `App\Exceptions\PlanLimitExceededException`, rendered the same way with `error_code: "plan_limit_exceeded"` and `errors: { limit, current, max, upgrade_url }` — one shared shape (`App\Support\PlanGateResponse`) so the dashboard can build a single "upgrade your plan" prompt component regardless of which check failed.

## 7.6 Store, Branch & Kiosk Endpoints
_Empty — populated in Phase 4._

## 7.7 Catalog Endpoints
_Empty — populated in Phase 5._

## 7.8 Try-On Endpoints
_Empty — populated in Phase 6._

## 7.9 Campaign Endpoints
_Empty — populated in Phase 7._

## 7.10 Loyalty Endpoints
_Empty — populated in Phase 8._

## 7.11 Customer Engagement Endpoints
_Empty — populated in Phase 9._

## 7.12 Analytics Endpoints
_Empty — populated in Phase 10._

## 7.13 Notification Endpoints
_Empty — populated in Phase 11._

## 7.14 Integration & Webhook Endpoints
_Empty — populated in Phase 12._

## 7.15 Mission Control Endpoints
Foundation (auth + health) shipped in Phase 1.C; tenant approval/plan/revenue endpoints ship in Phase 13. Every route below is mounted at `/api/v1/mission` (see `routes/api_mission.php`) and authenticates against the `super_admin` guard — a tenant `sanctum` token is always rejected, and vice versa.

### `GET /api/v1/mission/health`
Unauthenticated liveness probe for the Mission Control surface. Checks the database connection and — unlike the tenant health endpoint — whether at least one `super_admins` row exists, so a fresh deploy that forgot to run `SuperAdminSeeder` is visible immediately as `degraded` rather than surfacing later as "nobody can log in."

**Auth:** none. **Plan:** none.

Response `200` (healthy):
```json
{
  "success": true,
  "message": "Mission Control API is healthy.",
  "data": {
    "status": "ok",
    "checks": { "app": true, "database": true, "super_admin_seeded": true },
    "timestamp": "2026-08-14T08:07:16+00:00"
  }
}
```

### `POST /api/v1/mission/login`
Issues a Sanctum token for a `super_admins` row. Rejects an unknown email/wrong password with the same `invalid_credentials` error code (no user enumeration), and rejects a correct password on a suspended account with `super_admin_suspended`. Updates `last_login_at` on success. Throttled via the shared `auth` rate limiter (5/min per IP+email).

**Auth:** none. **Plan:** none.

Request body:
```json
{ "email": "owner@fitmirror.com", "password": "••••••••" }
```
Response `200`:
```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "token": "1|abcdef...",
    "super_admin": { "id": 1, "name": "Product Owner", "email": "owner@fitmirror.com", "role": "super_admin", "status": "active" }
  }
}
```
Response `401` (`invalid_credentials`) / `403` (`super_admin_suspended`) / `422` (missing `email`/`password`).

### `POST /api/v1/mission/logout`
Revokes the bearer token used on the request (`currentAccessToken()->delete()`), not every token the account holds — logging out one browser tab never logs out another device.

**Auth:** `super_admin` (active account only). **Plan:** none.

Response `204`: empty body.

### `GET /api/v1/mission/me`
The Mission Control analog of the tenant API's `GET /api/v1/user`. Returns the authenticated super admin's profile, resolved role permissions, and 2FA status.

**Auth:** `super_admin` (active account only). **Plan:** none.

Response `200`:
```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "id": 1,
    "name": "Product Owner",
    "email": "owner@fitmirror.com",
    "role": "super_admin",
    "role_label": "Super Admin",
    "status": "active",
    "permissions": ["tenants", "plans", "billing", "ops", "support"],
    "two_factor_enabled": false,
    "last_login_at": "2026-08-14T08:20:00+00:00"
  }
}
```

### `POST /api/v1/mission/impersonate/{user}` (Phase 2.C)
Issues a 30-minute Sanctum token scoped to a single tenant `User`, so support/Super Admin can reproduce a tenant's exact dashboard view. `{user}` is a plain integer id resolved via `User::withoutTenantScope()` — Mission Control has no tenant context of its own, and this is documented as the fourth deliberate `TenantScope` bypass (PROGRESS.md Decision D-13). Requires the `SuperAdminPermission::Tenants` permission (Super Admin and Support roles only, not Finance). Records an `impersonations` row and an `App\Models\Activity` entry (`log_name: 'impersonation'`) visible in the tenant's own `GET /audit-log` — impersonation is fully transparent to the tenant, never silent.

**Auth:** `super_admin` (active account, `tenants` permission). **Plan:** none.

Request: `{ "reason": "Support ticket #42" }` (optional).
Response `200`: `{ "data": { "token": "...", "expires_at": "...", "user": { "id", "name", "email" }, "tenant_id": 1 } }`

The dashboard SPA (Phase 2.D's `ImpersonationBanner`) is opened in a **new browser tab** at `{FRONTEND_URL}/?impersonation_token=...&impersonated_by=...` — the super admin's own Mission Control session is never touched, so "exit impersonation" (`POST /auth/impersonation/exit`, §7.3) only ever needs to revoke that one token and let the tab close.

### `POST /api/v1/mission/tenants/{tenant}/payments` (Phase 3.C)
Records a payment collected outside SSLCommerz (bank transfer, cash, a negotiated deal) — same `Invoice`/`Subscription` state machine as a real gateway payment (the `Invoice` is marked `Paid`, the `Subscription` transitions `PendingPayment → PendingApproval`), just with no gateway round trip. `{tenant}` is a plain integer id.

**Auth:** `super_admin` (active account, `billing` permission — Finance or Super Admin roles only).

Request: `{ "plan_id": 2, "billing_cycle": "monthly", "amount": null, "note": "Bank transfer confirmed by ops" }` (`amount` optional — defaults to the plan's own listed price for that cycle; set it only to record a negotiated/partial amount. `note` optional.)
Response `201`: `{ "data": { "id", "invoice_number", "amount", "currency", "status" } }`

### `POST /api/v1/mission/payments/{payment}/refund` (Phase 3.C)
Issues a refund against a previously successful `Payment` — `App\Services\Billing\RefundService`. For an SSLCommerz payment this calls SSLCommerz's refund API using the `bank_tran_id` captured from that payment's stored order-validation payload; for a `manual` (offline-recorded) payment it completes without any gateway call. A `refunds` ledger row is always created, even when the gateway call itself throws (`status: "failed"`), so a refund attempt is never silently lost. `{payment}` is a plain integer id, resolved via `Payment::withoutTenantScope()`.

**Auth:** `super_admin` (active account, `billing` permission — Finance or Super Admin roles only).

Request: `{ "amount": null, "reason": "Tenant application rejected" }` (`amount` optional — defaults to the full original payment amount; `reason` optional.)
Response `201`: `{ "data": { "id", "amount", "status", "gateway_refund_ref" } }`
Response `502` (`payment_gateway_error`) if the payment is an SSLCommerz payment missing a `bank_tran_id`, or if `SSLC_REFUND_INITIATE_ENDPOINT` isn't configured (see Decision D-18 — this endpoint's exact path was never verified against a live SSLCommerz sandbox).

This is the manual counterpart to the still-SKIPPED "auto-refund trigger when Mission Control rejects a tenant" (PROGRESS.md Phase 3.C) — until Phase 13 builds a tenant-reject action to fire it automatically, this is how Support/Finance issue a refund today.

## 7.16 Public REST API (Max Plan)
_Empty — populated in Phase 12._

---

# 8. Module Documentation

Each module below is documented with: purpose, data model, key services and classes, business rules, events fired, jobs, plan gating, API surface, and frontend surface. Sections are filled as the module is built.

## 8.1 Virtual Try-On Engine

**Purpose:** Real-time AR garment overlay on a live camera feed at the kiosk or on the customer's phone.

**Feature scope (from the product document):**
- Real-time AR overlay via pose estimation
- Face + body detection for accurate garment fitting
- Color variant switching in one click
- Multi-outfit layering (panjabi + coat, kurti + orna)
- Body size estimation with size suggestion
- Occasion filter (Wedding, Eid, Office, Casual, Party)
- Multi-person try-on (couple / family)
- Outfit matching AI suggesting shoes / bags / orna (upsell)
- Snapshot capture and share to WhatsApp / Email / Instagram
- QR code handoff for try-on on the customer's own phone
- Optional session recording (tenant opt-in)
- Kiosk screensaver mode with trending outfits

**Privacy rule (non-negotiable):** raw camera frames are never written to disk or object storage. Only the composited snapshot, if the customer explicitly captures one, is stored — and it is purged per the tenant's retention setting.

_Implementation details, models, and services: to be filled in Phase 6._

## 8.2 Product & Catalog Management

**Purpose:** Multi-level catalog with AR-ready assets, inventory, and sharing.

**Feature scope:** multi-level categories, product upload with variants and images, bulk Excel/CSV import with validation, AI background removal producing AR-ready PNG, size chart integration, product tagging, inventory with threshold alerts and auto-hide, season/expiry scheduling, digital catalog links, Facebook catalog sync, price history.

_To be filled in Phase 5._

## 8.3 Campaign Manager

**Purpose:** In-product marketing without third-party tools.

**Campaign types:** Seasonal, Flash Sale, New Arrival, Clearance, Birthday, Referral, Bundle Offer, Loyalty Point multiplier.
**Delivery channels:** kiosk banner, SMS blast, email newsletter, WhatsApp broadcast (Max), push notification, digital catalog link, social media auto-post.
**Builder:** drag-and-drop designer, 30+ pre-made templates, audience targeting, scheduling, A/B testing, budget cap, UTM tracking, analytics.

_To be filled in Phase 7._

## 8.4 Loyalty & Rewards

**Purpose:** Shop-configurable loyalty to drive repeat visits and reduce churn.

**Feature scope:** configurable point earning rules, redemption, Silver/Gold/Platinum tiers, birthday bonus, referral points, point expiry, QR digital loyalty card, leaderboard, customer-facing loyalty dashboard.

_To be filled in Phase 8._

## 8.5 Customer Engagement

**Purpose:** CRM-lite plus the interaction surfaces around try-on.

**Feature scope:** wishlist with price-drop notification, customer profiles with size preference and purchase history, review and rating with shop reply, fit feedback feeding size suggestions, appointment booking, live chat widget, notification preferences, guest try-on.

_To be filled in Phase 9._

## 8.6 Store & Staff Management

**Purpose:** Multi-branch operations and staff control.

**Feature scope:** multi-branch support, inter-branch stock check, Owner/Manager/Staff role system, activity audit log, shift management, staff performance reports, kiosk hours configuration, store profile, franchise management, custom domain (Max).

**Owner/Manager/Staff role system and activity audit log — built in Phase 2.C** (the rest of this section — multi-branch, shifts, kiosk hours — is still Phase 4):
- Roles are seeded by `RolePermissionSeeder` from the module × action matrix in `config/permissions.php` — Owner gets every permission (`*`), Manager gets everything except billing/tenant-settings/staff-deletion, Staff is read-only across catalog/customer/report modules. See PROGRESS.md Decision D-14 for why these are global, name-shared spatie roles rather than spatie's per-tenant "teams" feature (not needed until Max-plan custom roles exist).
- Staff join via invite/accept, never direct creation — `StaffInvitationService` (`app/Services/Staff/`). An invitation is a `staff_invitations` row with a sha256-hashed token; no `User` exists until acceptance.
- The tenant owner (`Tenant::owner_id`) is structurally immutable through this surface: no permission grant lets anyone but the owner themselves change, deactivate, or delete that account (`UserPolicy`).
- Activity logging: `App\Models\Activity` replaces spatie/laravel-activitylog's own model, adding a `tenant_id` column filled the same way as every other `BelongsToTenant` model — the audit log (`GET /audit-log`, §7.3a) is itself tenant-isolated by `TenantScope`, not just filtered in the query. Only `Tenant` and `User` log activity today (the only two real tenant-facing models); every model added in a later phase should add `LogsActivity` + a `getActivitylogOptions()` the same way.
- Mission Control impersonation (`POST /api/v1/mission/impersonate/{user}`, §7.15) is not part of this module's own RBAC — it's a super-admin capability, gated by `SuperAdminPermission::Tenants`, that issues a short-lived token *as* a tenant user. Every impersonation is itself an audit-logged event the tenant can see.

_Multi-branch, shift management, kiosk hours, franchise, and custom domain: to be filled in Phase 4._

## 8.7 Analytics & BI

**Purpose:** Turn try-on behaviour into buying and staffing decisions.

**Feature scope:** try-on heatmap, conversion funnel, peak hours, category performance, customer demographics, revenue reports with YoY, campaign ROI, loyalty analytics, dead stock alerts, retention rate, wishlist trend, PDF/Excel export.

_To be filled in Phase 10._

## 8.8 Subscription & Billing

**Purpose:** Monetization through SSLCommerz with owner-controlled activation.

**Feature scope:** SSLCommerz integration (bKash, Nagad, Rocket, VISA, MasterCard, DBBL), manual owner approval, auto renewal with grace period, prorated billing, branded PDF invoices, configurable trial, coupons, dunning retries on days 1/3/7, offline payment confirmation, VAT invoices, refund management.

**Plans & limits, and the non-payment half of the subscription lifecycle — built in Phase 3.A/3.B:**
- Free/Pro/Max seeded verbatim from the product document's §15 comparison table (`PlanSeeder`) — ৳০/৳৪৯৯/৳১,২৯৯ per month, yearly price = monthly × 12 × 0.8 (the 20% annual discount).
- `PlanService::resolve()` is the one place "what does this tenant's plan allow" is answered — falls back to Free for a tenant with no `plan_id` yet, since checkout (Phase 3.E) doesn't exist.
- `FeatureGate` (plan entitlement) and `FeatureFlag` (platform-wide kill switch, independent of any tenant) are two orthogonal gates — a route can depend on either or both.
- `SubscriptionStatus` is a *separate* state machine from `TenantStatus` (App\Enums\TenantStatus's own docblock, written in Phase 2.A, anticipated this) — the billing relationship vs. whether the tenant can use the product at all. `Subscription::transitionTo()` enforces a fixed, deliberate transition graph and throws on any edge not in it.
- Trial start (`SubscriptionService::startTrial()`) and cancellation (`SubscriptionService::cancel()`, `POST /subscription/cancel` — §7.5) are built; subscribe/upgrade/downgrade/auto-renewal/dunning are not — every one of those genuinely needs Phase 3.C's SSLCommerz integration to exist first (either to take a payment or to know what "prorated credit" even means without an invoicing ledger). See PROGRESS.md Phase 3.B for the full accounting of what's built vs. deliberately deferred.

**SSLCommerz payment integration — built in Phase 3.C:**
- `PaymentService::initiate()` creates (or resumes) a `PendingPayment` `Subscription` + `Pending` `Invoice`, then calls SSLCommerz's session-initiate API. The customer pays on SSLCommerz's own hosted page — this app never touches card/mobile-wallet details directly.
- Verification is server-to-server, not a client-side signature: SSLCommerz's `validationserverAPI` order-validation call is the source of truth, checked for both `status` and a matching `amount` before anything is marked paid. Both the success callback (browser redirect) and the IPN webhook (async, gateway-initiated) converge on the same `PaymentService::verifyAndMarkSuccess()`, which is idempotent — a replayed `tran_id` is a no-op past the first successful verification.
- A verified payment marks the `Invoice` `Paid` and moves the `Subscription` `PendingPayment → PendingApproval` — the exact transition Phase 3.B's state machine already had a slot for. No new `TenantStatus` was needed: `Pending`'s own label is already "Pending Approval" (§1.4's product flow), so the tenant side of the approval flow needed zero changes.
- Mission Control's manual/offline payment recording (`POST /mission/tenants/{tenant}/payments`) and refund issuance (`POST /mission/payments/{payment}/refund`) reuse this exact same state machine — an offline payment activates a subscription the same way a verified SSLCommerz payment does, just without the gateway round trip.
- Every gateway interaction (session-initiate request/response, order-validation response, each callback body) is kept on `payments.raw_payload`, keyed by round trip — `Payment::appendRawPayload()` — for reconciliation and disputes.
- Not yet live-verified: no SSLCommerz sandbox account exists (see PROGRESS.md's blocker note), so every test runs against `Http::fake()`. The refund API's exact endpoint path is also unconfirmed (Decision D-18) — `RefundService` works, but throws a clear error until `SSLC_REFUND_INITIATE_ENDPOINT` is set.

**Coupons, add-ons, VAT, and invoicing — built in Phase 3.D:**
- `CouponService::preview()` is the one place a coupon code is validated (active, within its start/expiry window, applies to the chosen plan, under both its global and per-tenant redemption caps) and priced (`Coupon::discountFor()` — percentage or fixed, always capped at the subtotal so a discount can never exceed it). No cart is persisted anywhere: a redemption is only ever written (`CouponRedemption`) when a real `Invoice` is created, at `PaymentService::resolvePendingInvoice()` — see Decision D-19.
- `App\Support\TaxCalculator::vatFor()` (`config('tax.vat_rate')`, default 15%) is applied to every plan-purchase and add-on invoice's post-discount subtotal. Mission Control's manual/offline payments are the one deliberate exception — the amount Finance types in is taken as the final total as-is, not run through VAT (`PaymentService::recordOffline()`'s own docblock).
- `App\Services\Billing\AddonPurchaseService` reuses the exact same `GatewayCheckoutService` (extracted from `PaymentService::initiate()` specifically for this) that plan purchases use — an add-on `Invoice` sets `addon_id`, never `subscription_id`, the mutually-exclusive counterpart to a plan invoice. A verified payment credits a new `TenantAddon` row (`unit_amount × quantity`); `App\Services\Billing\AddonConsumptionService` draws it down FIFO by purchase date — no real caller yet (SMS sending is Phase 11, storage tracking is Phase 5), tested directly as a primitive ahead of its callers.
- Every add-on (SMS pack, storage pack, priority support, template pack) is modelled uniformly as a consumable balance (`addons.unit_amount`, never null) rather than giving non-quantifiable ones like "priority support" a special boolean/flag shape — see Decision D-19.
- `PaymentService::finalizeInvoice()` (renamed from the Phase 3.C `markInvoicePaidAndActivateSubscription()`) now branches on which of `subscription_id`/`addon_id` is set, and unconditionally dispatches `GenerateInvoicePdfJob` — every paid invoice, plan or add-on, gets a real branded PDF (`resources/views/pdf/invoice.blade.php`, rendered via `barryvdh/laravel-dompdf`, saved to the `tenant` disk) and an email with it attached (`SendInvoiceEmailJob` → `App\Mail\InvoiceMail`).
- `GET /billing/invoices` (+ `/download`) and `GET /billing/history` (payments + refunds merged) give the tenant owner a real billing back-office; both require the `billing.view` permission that's existed in `config/permissions.php` since Phase 2.C but had nothing to gate until now.

**Frontend — built in Phase 3.E:** `PricingPage` (public, reads `GET /plans` live rather than hardcoding the comparison table), `CheckoutPage` (coupon field + order summary, hands off to SSLCommerz via `window.location.href`), `PaymentResultPage` (one page keyed by the `:status` route param the backend's callback controllers redirect to), `BillingDashboardPage` (current plan, usage meters, auto-renew toggle, cancel), `InvoicesPage`, `AddonsPage` — all in `apps/dashboard/src/pages/billing/`. Plus two reusable components any future module can use: `PlanLimitModal` (parses `PlanGateResponse::limitExceeded()`'s exact error shape) and `FeatureLockedOverlay` (reads the `features` `GET /auth/me` now returns). Verified live against the real backend, not just build/lint/test — see PROGRESS.md Phase 3.E's own note.

**Deliberately not built:** upgrade/downgrade with proration (still SKIPPED — needs a real subscription period end date nothing populates yet, see PROGRESS.md Phase 3.B/3.E) and auto-renewal/dunning jobs (same blocker as before, a renewal job that would actually charge a recurring payment).

## 8.9 Notification System

**Purpose:** Reach tenants and customers on the right channel at the right time.

**Feature scope:** in-app bell with realtime updates, email, SMS for critical alerts, push notifications, automated triggers (low stock, campaign end, payment failure), weekly digest email, Mission Control tenant alerts.

_To be filled in Phase 11._

## 8.10 Integrations

**Purpose:** Connect FitMirror to the tools shops already use.

**Feature scope:** SSLCommerz, Facebook Catalog API, Google My Business, WhatsApp Business API, POS 2-way stock sync, Google Analytics, Zapier/n8n, public REST API (Max), outbound webhooks, Bangladesh SMS gateway.

_To be filled in Phase 12._

## 8.11 Security

**Purpose:** Protect tenant, staff, and customer data.

**Feature scope:** AES-256 at rest and TLS 1.3 in transit, camera frame auto-delete, mandatory 2FA for admins, granular role permission matrix, brute force lockout, per-tenant rate limiting, SQL injection and XSS protection, quarterly vulnerability scans, GDPR-inspired right to erasure, daily automated backups with 30-day retention.

**Authentication pieces shipped in Phase 2.B** (the rest of this section's scope — vulnerability scans, backups, GDPR erasure — is still Phase 14):

- **Password policy** (`App\Providers\AppServiceProvider::configurePasswordPolicy()`): every `Password::defaults()` rule in the app — registration, reset, change — enforces min 10 chars, mixed case, a number, a symbol, and a Have I Been Pwned breach check (`uncompromised()`, k-anonymity API, no plaintext password ever leaves the server).
- **TOTP 2FA** (`App\Services\Auth\TwoFactorService`, `pragmarx/google2fa`): secret and recovery codes stored via Laravel's `encrypted`/`encrypted:array` casts, never in plaintext. Recovery codes are additionally one-way hashed and single-use (consumed on match). Mandatory for tenant owners (`App\Http\Middleware\EnsureTwoFactorIsEnabled`, alias `tenant.2fa`) once a business route needs it — not yet mandatory for super admins, since Mission Control has no self-service 2FA setup UI of its own yet (see PROGRESS.md Phase 2.B).
- **Progressive account lockout** (`App\Models\LoginAttempt`, `App\Services\Auth\LoginService`): independent of and in addition to the per-minute `throttle:auth` rate limiter — 5 consecutive failed attempts since the account's last success locks it out for `5 × (failures − 4)` minutes, so a sustained brute-force attempt faces an ever-longer wait rather than a fixed one.
- **Tenant isolation** (`TenantScope` fails closed): see §4.4.4 and PROGRESS.md Decision D-13 — the single most consequential security property of the whole backend, since every tenant-owned table depends on it.

_The rest of this section (encryption at rest for file storage, TLS, camera frame deletion, vulnerability scans, backups, GDPR erasure) is still to be filled in as those phases land — Phase 5 (media), Phase 6 (camera privacy), Phase 14 (the rest)._

## 8.12 Mission Control

**Purpose:** The Product Owner's single control centre for the whole platform.

**Feature scope:** tenant management with approval/rejection and impersonation, plan and pricing control with feature flags, revenue dashboard (MRR, ARR, churn, ARPU), platform operations (maintenance mode, announcements, email blasts, system health, backups), support tickets and in-platform messaging, full audit log.

**Foundation shipped in Phase 1.C** — auth is the only thing that exists so far; tenant approval and everything else above is still Phase 13.

- **Data model:** `super_admins` (§6.3) — completely separate from `users`. No `tenant_id`, since a super admin belongs to the platform, not a tenant.
- **Auth guard:** `super_admin`, driven by Sanctum against the `super_admins` Eloquent provider (`config/auth.php`). A tenant's `sanctum`-guard token is structurally incapable of authenticating here — Sanctum resolves the token to its `tokenable`, and the guard only accepts a `tokenable` of type `App\Models\SuperAdmin`.
- **Middleware:** every authenticated route runs `['auth:super_admin', 'super_admin']`. The first checks the token; the second (`App\Http\Middleware\EnsureSuperAdmin`) additionally rejects a structurally-valid token belonging to a `status = suspended` account — a check Sanctum's guard has no concept of on its own.
- **Roles & permissions:** `App\Enums\SuperAdminRole` (`super_admin`, `support`, `finance`) each map to a fixed `App\Enums\SuperAdminPermission` set (`tenants`, `plans`, `billing`, `ops`, `support`) via `SuperAdminRole::permissions()`. Per-route permission enforcement (`->middleware('super_admin.permission:billing')`) lands with the Phase 13 controllers that actually need it — the enum and mapping exist now so those routes have something real to gate against.
- **Bootstrap account:** `database/seeders/SuperAdminSeeder.php`, reading `SUPER_ADMIN_NAME`/`SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` from `.env`. Idempotent (`firstOrNew` keyed on email) — re-running `php artisan db:seed` never overwrites a password the Product Owner has since changed through the app.
- **API surface:** see §7.15 (`/health`, `/login`, `/logout`, `/me`).
- **Frontend:** `apps/mission-control` — `MissionLogin` (real form: client-side email/required-field validation, loading state, server error surfaced inline), `useMissionAuthStore` (Zustand, persists the profile; the bearer token itself lives in `@fitmirror/api`'s shared `tokenStorage`), and `routes/ProtectedLayout.tsx` (redirects an unauthenticated visit to `/login`, remembering the attempted path; wraps everything else in `MissionShell`). Verified end-to-end with a live `php artisan serve` + `vite dev` run, not just unit tests — see PROGRESS.md Phase 1.C.

---

# 9. Deployment Guide — For Product Owner (SaaS Owner)

_This section is written with exact, copy-pasteable commands as Phase 16 is completed. The outline below defines what will be documented._

## 9.1 Server Provisioning
Choosing a VPS, sizing per Section 2.4, creating a deploy user, SSH key setup, firewall (UFW), fail2ban, automatic security updates.

## 9.2 Installing the Stack
Exact `apt` commands for PHP 8.2-FPM and extensions, Nginx, MySQL 8, Redis, Supervisor, MeiliSearch, Node.js, Composer, and Certbot.

## 9.3 Database Setup
Creating the production database and user with least privilege, tuning `innodb_buffer_pool_size`, enabling slow query log.

## 9.4 Deploying the Application
Cloning the repository, `composer install --no-dev --optimize-autoloader`, building frontend assets, permissions on `storage/` and `bootstrap/cache`, atomic release directories and symlink switch.

## 9.5 Nginx & Domain Configuration
API server block, wildcard `*.fitmirror.com` for tenant subdomains, separate server blocks for dashboard/kiosk/portal/mission-control static builds, custom domain handling, security headers, gzip/brotli.

## 9.6 SSL Configuration
Let's Encrypt wildcard certificate via DNS-01 challenge, auto-renewal cron, on-demand certificates for tenant custom domains, HSTS.

## 9.7 Production `.env`
Every value that must change from development, with a checklist: `APP_ENV=production`, `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, live SSLCommerz keys, SES credentials, production S3 bucket, Sentry DSN.

## 9.8 Running Migrations in Production
```bash
php artisan down --render="errors::503"
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
```
Plus the pre-migration backup step and rollback procedure.

## 9.9 Queue Workers & Supervisor
Supervisor program definitions for `horizon` and `reverb:start`, restart-on-deploy hook (`php artisan horizon:terminate`), monitoring worker health.

## 9.10 Cron / Laravel Scheduler
```bash
* * * * * cd /var/www/fitmirror/current && php artisan schedule:run >> /dev/null 2>&1
```
Plus the full list of scheduled jobs and their cadences.

## 9.11 SSLCommerz Live Configuration
Merchant account activation, obtaining live store ID/password, whitelisting callback and IPN URLs in the merchant panel, running a live ৳10 verification transaction, switching `SSLC_SANDBOX=false`.

## 9.12 Monitoring & Logs
Sentry alert rules, Horizon dashboard access, log locations and rotation, uptime monitoring, key metrics to watch daily.

## 9.13 Backups & Restore
Daily database dump to S3, media bucket versioning, 30-day retention, the exact restore procedure, and a scheduled restore drill.

## 9.14 Post-Deployment Checklist
Full go-live verification list.

---

# 10. Tenant Onboarding Guide — For Product Owner

_Populated with real screens and steps as Phases 3 and 13 complete. Outline below._

## 10.1 How a Shop Owner Signs Up
Registration form fields, email verification, plan selection, payment through SSLCommerz. The account is created immediately but sits in `pending_approval` — it cannot access the dashboard.

## 10.2 How You Are Notified
On verified payment, Mission Control receives an in-app notification and an email to `SUPER_ADMIN_EMAIL`. The tenant appears at the top of the **Pending Approvals** queue with payment details attached.

## 10.3 Reviewing an Application
What to check before approving: shop name and address plausibility, phone verification, payment amount matching the selected plan, duplicate/abuse signals, and any internal notes.

## 10.4 Approving
One click. Behind the scenes: subscription activates with the correct period, the default store is provisioned, Owner role is assigned, a welcome email plus SMS is sent in Bangla, and the dashboard unlocks immediately.

## 10.5 Rejecting
A reason is required. Behind the scenes: an automatic refund is initiated through SSLCommerz, the tenant is emailed the reason, and the account is marked `rejected`. Refund status is tracked in the refunds ledger.

## 10.6 Managing Existing Tenants
Suspending (immediate access revocation with a reason shown to the tenant), reactivating, forcing a plan change without payment, extending trials or expiry dates, and impersonating an account for support — every one of these is written to the Mission Control audit log.

## 10.7 Managing Plans & Limits
Editing prices, limits (categories, SKUs, sessions/day, staff, branches, storage), and feature flags from Mission Control — no code change, no deploy. Creating a custom Enterprise plan for a single negotiated client.

## 10.8 Handling Payments & Refunds
Recording an offline payment, reviewing outstanding invoices, processing a refund, and reading the dunning retry state for a failed renewal.

---

# 11. Subscriber Guide — For Shop Owners (Tenants)

_Written in step-by-step form with screenshots as the UI is built. Outline below. A Bangla version is published alongside it at launch._

## 11.1 Buying a Plan
Choosing Free/Pro/Max, monthly vs yearly (20% off), applying a promo code, and paying with bKash, Nagad, Rocket, or card through SSLCommerz.

## 11.2 After Payment
Your account enters **pending approval**. You will receive an email and SMS when the FitMirror team approves it — typically within business hours. Nothing else is required from you in the meantime.

## 11.3 Setting Up Your Store
Store profile (logo, banner, address, contact, Google Map, social links), opening hours, and additional branches if your plan allows.

## 11.4 Adding Categories
Building your category tree (Boys → Panjabi/Shirt/T-shirt/Pant/Coat/Jacket, Girls → Saree/Threepiece/Kurti/Orna, and so on) within your plan's category limit.

## 11.5 Uploading Products
Single product upload with variants, colors, sizes, prices, stock, and images; bulk upload of hundreds of products from an Excel/CSV template; what "AR-ready" means and how to fix a product whose background removal did not come out clean.

## 11.6 Configuring the Kiosk
Pairing a device, setting display language and idle timeout, choosing the screensaver playlist, and setting kiosk active hours.

## 11.7 Launching a Campaign
Picking a campaign type, starting from a template, designing with the drag-and-drop builder, selecting products and discounts, targeting an audience, choosing channels, scheduling, and reading the results.

## 11.8 Setting Up Loyalty
Turning on the program, setting the earn rate and redemption value, configuring Silver/Gold/Platinum tiers, birthday bonuses, referral rewards, and point expiry.

## 11.9 Viewing Analytics
Reading the try-on heatmap, conversion funnel, peak hours, category performance, revenue trend, and campaign ROI — and exporting any report to PDF or Excel.

## 11.10 Managing Staff
Inviting staff, choosing between Manager and Staff roles, understanding what each role can see and do, assigning shifts, and reviewing the activity audit log.

## 11.11 Billing & Plan Changes
Viewing invoices, upgrading or downgrading (with proration), buying SMS or storage add-ons, and managing auto-renewal.

---

# 12. Kiosk Setup Guide

_Populated with real screens as Phase 6 completes. Outline and hardware guidance below._

## 12.1 Hardware Needed
- A tablet (10" or larger) or a laptop/all-in-one PC — see Section 2.5 for specifications
- A camera: the built-in one works; a 1080p wide-angle USB webcam is better
- A stand or wall mount placing the camera at roughly chest height, 1.5–2.5 m from where the customer stands
- Even front lighting; avoid a bright window directly behind the customer
- Stable internet (20 Mbps recommended)
- Optional: a floor marker showing the customer where to stand

## 12.2 Opening the Kiosk App
Navigating to the kiosk URL in Chrome/Edge, launching in kiosk/fullscreen mode, and the Android/Windows launcher options for a locked-down device.

## 12.3 Connecting the Kiosk to Your Store
Generating a pairing code from Dashboard → Kiosk Devices, entering it on the kiosk screen, and confirming the device shows as connected with a live heartbeat.

## 12.4 Granting Camera Permission
Allowing camera access on first launch, making the permission persistent, and what to do if the browser blocks it.

## 12.5 Display Settings
Language (বাংলা/English), theme and branding, idle timeout before screensaver, screensaver playlist, which categories appear, and the exit PIN for staff.

## 12.6 Positioning & Calibration
Camera height and distance, framing a full upper body, verifying pose detection quality, and a quick test with two people for couple try-on.

## 12.7 Daily Operation
Opening and closing the kiosk with store hours, what staff should do when a customer approaches, and how to help a customer send a snapshot to their phone.

---

# 13. Troubleshooting

_Populated with real errors and verified fixes as we build. Format below._

### Format used for every entry

**Symptom:** what the user sees
**Cause:** why it happens
**Fix:** exact commands or steps
**Prevention:** how to avoid it recurring

## 13.1 Installation & Setup Issues

### `pcntl` / `posix` missing — Horizon will not start on Windows

**Symptom:** `php -m` never lists `pcntl` or `posix`; running `php artisan horizon` fails with an error about missing PCNTL functions.
**Cause:** `pcntl` and `posix` are Unix-only PHP extensions. No Windows build exists — this is not a configuration mistake and cannot be fixed by editing `php.ini`.
**Fix:** On a Windows host, run the plain queue worker instead:
```bash
php artisan queue:work --queue=high,default,notifications,media,analytics,campaigns --tries=3
```
For the full Horizon dashboard, run the backend inside WSL2, Docker, or a Linux VM. Production runs Linux, where Horizon works normally.
**Prevention:** Treat the local queue runner as environment-specific. Never hard-code a dependency on Horizon-only behaviour in application code.

### PECL extension installed but `php -m` does not list it

**Symptom:** The DLL was copied into `ext\` and `extension=` was added to `php.ini`, but the extension does not appear in `php -m`. Sometimes the CLI prints a startup warning; sometimes it is silent.
**Cause:** The DLL build does not match the PHP build. All four must agree: PHP version (8.2), thread safety (ts/nts), compiler (vs16), and architecture (x64).
**Fix:** Check the target build and re-download the matching asset:
```bash
php -v
php -r "echo (PHP_ZTS ? 'ts' : 'nts'), ' ', PHP_INT_SIZE*8, '-bit', PHP_EOL;"
```
For PHP 8.2.12 Thread Safe x64 (VC2019), the asset suffix must be `-8.2-ts-vs16-x64.zip`.
**Prevention:** Record the exact build string in Section 2.2.2 and always download against it.

### Imagick loads but image operations fail with missing-DLL errors

**Symptom:** `extension_loaded('imagick')` is true, but format conversions fail or PHP crashes.
**Cause:** The ImageMagick runtime DLLs (`CORE_RL_*.dll`, `IM_MOD_RL_*.dll`) were left in `ext\` instead of the PHP root.
**Fix:** Copy every DLL except `php_imagick.dll` into `C:\xampp\php\`, leaving only `php_imagick.dll` in `C:\xampp\php\ext\`.
**Prevention:** Follow Section 2.2.3 exactly — the split between the two directories is deliberate.

### Composer reports security advisories

**Symptom:** `composer diagnose` ends with `Checking Composer and its dependencies for vulnerabilities: FAIL`.
**Cause:** An outdated Composer binary. Versions below 2.10.2 are affected by CVE-2026-59946 (path traversal via a package `bin` field).
**Fix:**
```bash
composer self-update
composer diagnose
```
**Prevention:** Run `composer self-update` at the start of each development cycle.

### PowerShell reports `NativeCommandError` on a command that actually succeeded

**Symptom:** A `composer` or `php` command completes correctly, but PowerShell prints a red `NativeCommandError` block.
**Cause:** Windows PowerShell 5.1 wraps native-executable stderr output in error records even when the exit code is `0`. Composer writes progress output to stderr by design.
**Fix:** Check the actual result (`composer --version`) rather than trusting the red text.
**Prevention:** Avoid redirecting native stderr (`2>&1`) inside PowerShell 5.1.

## 13.2 Database & Migration Issues

### Connected to MariaDB instead of MySQL 8

**Symptom:** `SELECT VERSION()` returns something like `10.4.32-MariaDB`. JSON columns behave oddly, or a migration that works locally fails in production.
**Cause:** The connection went to XAMPP's bundled MariaDB on port 3306 instead of FitMirror's MySQL 8.4 on port 3307. XAMPP names its MariaDB binaries and service `mysql`, which makes this easy to do by accident.
**Fix:** Set `DB_PORT=3307` in `.env`, then:
```bash
php artisan config:clear
php artisan tinker --execute="echo DB::selectOne('SELECT VERSION() v')->v;"
```
The result must start with `8.4`.
**Prevention:** Always pass `--port=3307` to `mysql.exe`, and prefer the full path `C:\mysql8\bin\mysql.exe` over whatever `mysql` resolves to on `PATH`.

### `MySQL84` service will not start

**Symptom:** `net start MySQL84` fails, or the service stops immediately after starting.
**Cause:** Usually a config error in `my.ini`, a port conflict on 3307, or a corrupt data directory.
**Fix:** Read the error log — it names the exact cause:
```bash
tail -50 /c/mysql8/data/mysql-error.log
```
Check whether something else holds the port:
```bash
netstat -ano | grep 3307
```
**Prevention:** Validate config changes with `mysqld --defaults-file=C:\mysql8\my.ini --validate-config` before restarting the service.

### `Authentication plugin 'caching_sha2_password' cannot be loaded`

**Symptom:** A database client or older PHP build refuses to connect to MySQL 8.4.
**Cause:** MySQL 8.4 **removed** `mysql_native_password`. Clients that only speak the old plugin cannot authenticate.
**Fix:** Use a client that supports `caching_sha2_password` — PHP 8.2's mysqlnd does, and is verified working. For a legacy GUI tool, upgrade the tool rather than weakening the server.
**Prevention:** Do not attempt to re-enable `mysql_native_password`; it is deprecated and absent from the default 8.4 build. Production runs 8.4 too, so any client must support the modern plugin.

### `SQLSTATE[42000]: Syntax error or access violation: 1142 ... denied`

**Symptom:** A query fails with a privilege error, often against a `mysql.*` or `performance_schema` table.
**Cause:** Working as intended. The `fitmirror` application user is deliberately scoped to the two FitMirror schemas and has no access to system tables.
**Fix:** If the query is genuinely needed for administration, run it as `root`. If application code triggers it, the code is doing something it should not.
**Prevention:** Never widen the application user's grants to work around this — that defeats the least-privilege setup.

### `Specified key was too long` on migration

**Symptom:** Migration fails with an index length error on a `VARCHAR` column.
**Cause:** `utf8mb4` uses 4 bytes per character, so a 255-character indexed column exceeds InnoDB's key limit under older row formats.
**Fix:** MySQL 8.4 defaults to the `DYNAMIC` row format with a 3072-byte limit, so this should not occur. If it does, set a default string length in `AppServiceProvider::boot()`:
```php
Schema::defaultStringLength(191);
```
**Prevention:** Index `VARCHAR(191)` columns rather than `VARCHAR(255)` where an index is required.

### Timestamps are off by six hours

**Symptom:** Analytics report the wrong day, or `created_at` values look shifted.
**Cause:** The database stores UTC (`default-time-zone='+00:00'`) while the application uses `Asia/Dhaka` (UTC+6). Something is converting twice, or not at all.
**Fix:** Keep all storage in UTC and convert only at the presentation layer. Confirm `APP_TIMEZONE=Asia/Dhaka` in `.env` and never set a per-connection timezone in `config/database.php`.
**Prevention:** Never store local time. Analytics rollups run against UTC and convert to the tenant's timezone when rendering.

## 13.3 Queue & Horizon Issues
_Empty — populated as encountered._

## 13.4 Payment & SSLCommerz Issues
_Empty — populated as encountered._

## 13.5 Kiosk & Camera Issues
_Empty — populated as encountered._

## 13.6 Try-On Rendering Issues
_Empty — populated as encountered._

## 13.7 Media, Storage & Background Removal Issues
_Empty — populated as encountered._

## 13.8 Email & SMS Delivery Issues
_Empty — populated as encountered._

## 13.9 Multi-Tenancy Issues
_Empty — populated as encountered._

## 13.10 Deployment & Production Issues
_Empty — populated as encountered._

---

# 14. Changelog

_Version history, updated at every release. Follows [Semantic Versioning](https://semver.org/) and [Keep a Changelog](https://keepachangelog.com/) conventions._

## [Unreleased]

### Added
- `PROGRESS.md` — full 827-task build checklist across 16 phases
- `DOCUMENTATION.md` — this document, structured and ready to fill
- Development toolchain verified: PHP 8.2.12 (TS x64) and Composer 2.10.2
- PHP extensions installed on the dev host: `intl` (enabled), `redis` 6.3.0, `imagick` 3.8.1
- §2.2.1 platform note on `pcntl`/`posix` and the Windows Horizon constraint
- §2.2.2 verified development machine record
- §2.2.3 guide to installing PECL extensions on Windows/XAMPP
- §13.1 five troubleshooting entries covering setup issues encountered
- Decision D-01 recorded: development runs on the Windows/XAMPP host with `queue:work`; Horizon executes in production only
- MySQL 8.4.8 LTS installed at `C:\mysql8` on port 3307, running as the `MySQL84` Windows service
- `fitmirror` and `fitmirror_testing` databases created with `utf8mb4` / `utf8mb4_unicode_ci`
- Least-privilege `fitmirror` database user created, scoped to the two FitMirror schemas
- §2.4.1 MySQL vs MariaDB engine rationale (decisions D-02, D-03)
- §2.4.2 full MySQL 8.4 Windows installation guide with verified configuration
- §3.4 rewritten with real database and user creation commands
- §13.2 six database troubleshooting entries
- Redis 7.2.5 installed via Memurai Developer 4.1.2 as the `Memurai` Windows service on port 6379
- §2.4.3 Redis-on-Windows options analysis and Memurai setup (decision D-04)
- MeiliSearch 1.50.0 installed at `C:\meilisearch` on port 7700 with master-key auth and boot auto-start
- §2.4.4 MeiliSearch Windows setup, management commands, and verified configuration
- §2.4.5 Node.js toolchain, nvm usage, and verified configuration (decision D-05)
- Laravel 12 project scaffolded in `backend/`; Decision D-06 recorded (Laravel 12, not 11 — see PROGRESS.md)
- Decision D-07 recorded: `APP_TIMEZONE=UTC`, `TENANT_DEFAULT_TIMEZONE=Asia/Dhaka` for presentation-layer conversion
- Decision D-09 recorded: no Repository pattern — `BaseService` + Eloquent query-builder scopes instead (§4.4.2)
- Laravel Sanctum installed and wired (`HasApiTokens` on `User`, config + migration published) — see Decision D-08
- Horizon queue definitions (`high, default, notifications, media, analytics, campaigns`) and dashboard gate (`app/Providers/HorizonServiceProvider.php`, keyed on `SUPER_ADMIN_EMAIL`)
- Telescope production guard — registered only in `AppServiceProvider` for `local`/`staging`, never reachable in production regardless of `.env`
- Sentry SDK configured: `config/sentry.php` published, `sentry` log channel added, `/api/v1/health` excluded from tracing noise
- Scout config published; MeiliSearch driver confirmed reachable end-to-end via `scout:sync-index-settings`
- `tenant` filesystem disk added to `config/filesystems.php` (local in dev, S3/R2 in production)
- `config/cors.php` created with an explicit origin allow-list (dashboard/kiosk/portal/mission-control) plus a tenant-subdomain pattern
- API versioning: `routes/api_v1.php` mounted at `/api/v1` via `apiPrefix` in `bootstrap/app.php`
- `App\Support\ApiResponse` and `App\Support\ApiExceptionRenderer` — the standard JSON success/error/paginated envelopes and the global exception-to-JSON mapping (Laravel 12 has no `Handler.php`; this is wired via `withExceptions()`)
- `App\Http\Requests\BaseFormRequest` and `App\Http\Controllers\Api\V1\BaseApiController`
- `App\Http\Middleware\ResolveLocale` — resolves `bn`/`en` from user preference, then `Accept-Language`, then config default
- Named rate limiters `api`, `auth`, `tenant`, `kiosk` in `AppServiceProvider` (Redis-backed automatically via `CACHE_STORE=redis`)
- `GET /api/v1/health` — see §7.2
- Dedicated `queue` and `session` Redis logical-database connections added to `config/database.php` (previously only `default`/`cache` existed despite `REDIS_QUEUE_DB`/`REDIS_SESSION_DB` already being in `.env`)
- `phpunit.xml` switched from sqlite `:memory:` to the real `fitmirror_testing` MySQL 8.4 database (Decision D-02)
- Larastan baseline (`phpstan.neon`, level 6, `phpstan-baseline.neon`) and Pint ruleset (`pint.json`) — both pass clean
- `docker-compose.yml`, `backend/Dockerfile`, `docker/nginx/default.conf` for the local Docker alternative
- `.github/workflows/ci.yml` — Pint, Larastan, migrate, PHPUnit against a real MySQL 8.4 + Redis service; frontend job stubbed disabled until Phase 1.B
- `lang/bn/*` and `lang/en/*` — Laravel's default `auth`, `pagination`, `passwords`, `validation` lines translated to Bangla, plus a shared `common.php` with FitMirror-specific keys in both locales
- `docs/BRANCHING.md` — git branching strategy (main/develop/feature/fix/hotfix)
- §4.4 Backend Naming & Layering Conventions — database naming rules and the service/query-builder convention (Decision D-09)
- `.nvmrc` pinning Node 22.18.0 at the repo root

### Changed
- Composer upgraded 2.8.2 → 2.10.2, clearing 9 security advisories including CVE-2026-59946
- §2.1 minimum database requirement raised from MySQL 8.0 to 8.4 LTS (8.0 reached EOL April 2026)
- §5.2 `DB_PORT` example corrected to `3307`; added `DB_CHARSET` and `DB_COLLATION`
- `DatabaseSeeder` rewritten as an ordered `$this->call([...])` registration point instead of inline seeding logic

### Fixed
- **Laravel Sanctum was never actually installed** despite being reported as done in the prior session summary — `routes/api.php` already referenced `auth:sanctum` middleware, which would have failed at runtime. See Decision D-08.
- Our customised `App\Providers\HorizonServiceProvider` (with the dashboard gate) was never registered in `bootstrap/providers.php` — only the base package provider was, via auto-discovery, so the gate had zero effect.
- Unauthenticated API requests without an explicit `Accept: application/json` header (e.g. a bare `curl` call) 500'd with `RouteNotFoundException` trying to redirect to a non-existent `login` route, instead of returning a 401 JSON error. Fixed via `$middleware->redirectGuestsTo(fn () => null)` in `bootstrap/app.php` — this is an API-only application with no web login route to redirect to.

---

## Phase 3.D — Coupons, Add-ons & Invoicing / Phase 3.E — Frontend Billing — 2026-08-18

### Added
- `coupons`/`coupon_redemptions`/`addons`/`tenant_addons` tables + `Coupon`/`CouponRedemption`/`Addon`/`TenantAddon` models; `CouponType`/`CouponStatus`/`AddonType`/`AddonStatus` enums; `invoices.addon_id` (nullable, mutually exclusive with `subscription_id`)
- `App\Services\Billing\{CouponService,GatewayCheckoutService,AddonPurchaseService,AddonConsumptionService,InvoicePdfService}`, `App\Support\TaxCalculator`, `config/tax.php` (`VAT_RATE`, default 15%)
- `App\Jobs\{GenerateInvoicePdfJob,SendInvoiceEmailJob}`, `App\Mail\InvoiceMail`, `resources/views/pdf/invoice.blade.php`, `resources/views/mail/invoice-paid.blade.php` — every invoice that reaches Paid (plan or add-on, online or Mission Control manual) now gets a real branded PDF stored on the `tenant` disk and emailed to the owner
- `POST /api/v1/billing/coupon/preview`, `GET /api/v1/billing/invoices` (+ `/download`), `GET /api/v1/billing/history`, `GET /api/v1/billing/addons` (+ `/purchase`), `GET /api/v1/subscription`, `PATCH /api/v1/subscription/auto-renew`, `GET /api/v1/plans` — see §7.5
- `PaymentService::initiate()` refactored: the SSLCommerz round trip itself is now `GatewayCheckoutService` (shared with `AddonPurchaseService`), and `resolvePendingInvoice()` runs every plan-purchase invoice through the new coupon + VAT pricing pipeline
- `AddonSeeder` — the four packs named in the checklist (SMS/storage/support/template), registered in `DatabaseSeeder`
- `MeController` now returns `features` alongside the existing `plan`/`limits` (Phase 3.A) — the data source for `FeatureLockedOverlay`
- Frontend: `PricingPage`, `CheckoutPage`, `PaymentResultPage`, `BillingDashboardPage`, `InvoicesPage`, `AddonsPage` (`apps/dashboard/src/pages/billing/`), `PlanLimitModal`/`FeatureLockedOverlay` (`components/billing/`), `lib/billing.ts` — see §7.5 for the endpoints these consume
- `dashboardAuthStore` now carries `plan`/`limits`/`features` (previously only `tenant.planSlug`, hardcoded to `'free'` regardless of the tenant's actual plan — see Fixed below) and `useTenantStore`'s `planSlug` is now populated from the real resolved plan
- 39 new backend feature tests (`tests/Feature/Billing/*`, 8 files) + `PlanListTest`/`CurrentSubscriptionTest` — 193 backend tests total, all passing
- Decision D-19 recorded: coupons have no persisted cart (stateless preview + write-on-redeem only) and every add-on is modelled as a uniform consumable balance pack, not a mixed balance/flag shape

### Fixed
- `TENANT_DISK_DRIVER=` ships as a *present-but-empty* string in `.env`, not absent — `env('TENANT_DISK_DRIVER', $default)` only falls back to `$default` when the key is missing entirely, so the `tenant` disk's driver silently resolved to `''` and every write threw "Disk [tenant] does not have a configured driver." This has been latent since Phase 1.A/2.A; nothing ever actually wrote to the `tenant` disk in a feature test until `InvoicePdfService` did. Fixed in `config/filesystems.php` with `env('TENANT_DISK_DRIVER') ?: (...)`, which treats both `null` and `''` as "not set."
- `App\Jobs\SendInvoiceEmailJob` eager-loading `$invoice->tenant->owner` resolved to `null` on every real queue worker — `User` carries `TenantScope`, and a queued job (Decision D-01: no cross-request/cross-job container reuse) has no ambient `TenantContext` for that relation query to run against. The exact class of bug Decision D-13 describes, this time in a job rather than a controller; fixed with an explicit `User::withoutTenantScope()->find($ownerId)` instead of the relation.
- `dashboardAuthStore.setSession()`/`setMe()` hardcoded `useTenantStore`'s `planSlug: 'free'` unconditionally, regardless of the tenant's actual plan — dead code until this phase (nothing read `planSlug` yet), now wired to the real `plan.slug` from `GET /auth/me`.
- `App\Support\PlanGateResponse::upgradeUrl()` pointed at `/settings/billing`, a placeholder written in Phase 3.A before any billing page existed — updated to the real `/billing` route now that one does.

### Known limitations, recorded rather than hidden
- Phase 3.B's upgrade/downgrade-with-proration API remains SKIPPED — the coupon/VAT half of what blocked it is resolved, but proration math needs a real subscription period end date (`subscriptions.ends_at`), which nothing populates until a renewal job exists. The Phase 3.E frontend task for this was left undone rather than built against fabricated data — see PROGRESS.md Phase 3.E's own note.
- No SSLCommerz sandbox account exists yet (carried forward from Phase 3.C) — every gateway interaction, including the new add-on purchase flow, is tested against `Http::fake()`.
- "Billing history" merges payments and refunds only — there is no credits/ledger concept anywhere in this codebase to include a third row type.
- Manual/offline payments (Mission Control) don't run through the VAT/coupon pipeline — the amount Finance types in is taken as the final total as-is, a deliberate scope decision (see `PaymentService::recordOffline()`'s docblock), not an oversight.

---

## Phase 3.C — Payments (SSLCommerz) — 2026-08-18

### Added
- `invoices`/`invoice_items`/`payments`/`refunds`/`invoice_number_sequences` tables + `Invoice`/`InvoiceItem`/`Payment`/`Refund` models; `InvoiceStatus`/`PaymentStatus`/`PaymentGateway`/`RefundStatus` enums
- `App\Services\Billing\{InvoiceNumberGenerator,SslCommerzService,PaymentService,RefundService}` and `config/sslcommerz.php`
- `POST /api/v1/payment/initiate`, `POST /api/v1/payment/callback/{success,fail,cancel}`, `POST /api/v1/payment/ipn` — see §7.5
- `POST /api/v1/mission/tenants/{tenant}/payments` (offline/manual recording) and `POST /api/v1/mission/payments/{payment}/refund` — Finance/Super Admin only, see §7.15
- `App\Exceptions\PaymentGatewayException`, rendered by `ApiExceptionRenderer` as `502 payment_gateway_error`
- A verified successful payment transitions the tenant's `Subscription` `PendingPayment → PendingApproval` (Phase 3.B's existing state machine) — no new tenant status was needed; see §1.4 and `PaymentService`'s class docblock for why `TenantStatus::Pending` already *is* the product document's "pending_approval" state
- 29 new backend feature tests (`tests/Feature/Payment/*`, 6 files) — 154 total, all passing; every SSLCommerz call is `Http::fake()`d (no sandbox credentials — see PROGRESS.md's carried-forward blocker)
- Decision D-18 recorded: SSLCommerz's refund endpoints are deliberately left unconfigured rather than hardcoded to an unverified guess, unlike the session-initiate/order-validation endpoints

### Fixed
- `$payment->invoice` (the plain `belongsTo` relation) silently returned `null` in every context this phase actually runs in — gateway callbacks, the IPN webhook, and Mission Control's manual-payment/refund actions all execute with no ambient `TenantContext`, so the lazy-loaded `Invoice` relation query inherited `TenantScope`'s fail-closed default independently of how the parent `Payment` was fetched (the same class of bug as Decision D-13's Sanctum `tokenable()` case). Fixed with a dedicated, explicitly-named `Payment::invoiceUnscoped()` accessor used at every Phase 3.C call site instead of the plain relation.
- `PaymentService::initiate()`'s first draft wrapped the SSLCommerz session-initiate HTTP call inside the same DB transaction as the Subscription/Invoice/Payment row creation — a gateway failure rolled the whole attempt back, leaving zero rows behind to reconcile against and silently defeating the checklist's "persist every gateway payload for reconciliation and disputes" requirement. Restructured so the rows commit first and the gateway call (with its own Failed-state update on error) happens afterward, outside any transaction.

### Known limitations, recorded rather than hidden
- No SSLCommerz sandbox account exists yet — every gateway interaction is tested against `Http::fake()`, never a real response. Live end-to-end verification (and the two refund endpoint paths) remains open; see PROGRESS.md's blocker note.
- Auto-refund trigger when Mission Control rejects a tenant: SKIPPED — no tenant-reject action exists yet (Phase 13). `RefundService::refund()` is the primitive that trigger will call once built.
- Phase 3.B's subscribe-with-coupon and upgrade/downgrade-with-proration APIs remain SKIPPED — Phase 3.C unblocked the payment half, but coupons and a proration/credit ledger are still Phase 3.D's work.
- Success/fail/cancel callbacks redirect to `config('app.frontend_url')/billing/payment/{status}` — Phase 3.E's actual result pages don't exist yet, the same "point at a frontend route ahead of the page being built" pattern already used for the password-reset email link and staff invitation emails.

---

## Phase 3.A — Plans & Limits / Phase 3.B — Subscription Lifecycle (partial) — 2026-08-17

### Added
- `plans`/`plan_limits`/`plan_features`/`feature_flags` tables + `Plan`/`PlanLimit`/`PlanFeature`/`FeatureFlag` models; `tenants.plan_id` finally gets its FK (deferred since Phase 2.A, `plans` didn't exist yet)
- `PlanSeeder` — Free/Pro/Max seeded verbatim from the product document's §15 comparison table (extracted directly from `FitMirror_Full_Product_Doc_v2.docx`, not re-derived from memory)
- `App\Services\Plan\{PlanService,FeatureGate,SubscriptionService}`, `App\Support\{UsageCounter,PlanGateResponse}`, `App\Http\Middleware\EnforcePlanFeature` (`plan.feature:{key}`), `App\Exceptions\PlanLimitExceededException`
- `php artisan usage:reset` — Redis usage counters, scheduled daily at 00:00 Asia/Dhaka
- `GET /api/v1/plan/usage` — see §7.5
- `subscriptions` table, `SubscriptionStatus`/`BillingCycle` enums, `Subscription` model with a full transition-graph state machine (`canTransitionTo()`/`transitionTo()`)
- Trial start flow (`SubscriptionService::startTrial()`) and cancel API (`POST /api/v1/subscription/cancel`, immediate or end-of-period, with reason capture) — see §7.5
- Plan-based staff-account limit enforcement wired into `StaffInvitationService`, completing the item Phase 2.C had to leave SKIPPED
- `GET /auth/me` now returns the tenant's real resolved `plan`/`limits` instead of `null`/`[]`
- 27 new backend feature tests (`tests/Feature/Plan/*`, 6 files) — 126 total, all passing
- Decision D-17 recorded: two verified phpredis `SCAN` quirks (cursor must start `null`; `match` needs the connection's key prefix, `del()` doesn't want it back)

### Fixed
- `Subscription`/other `BelongsToTenant` models looked up via an explicit `$tenant` parameter (not an authenticated request's ambient `TenantContext`) returned zero rows even for that exact tenant — `TenantScope`'s fail-closed default (Decision D-13) has no way to know the caller already knows which tenant it means. Fixed with `withoutTenantScope()` at the two call sites that need it (`SubscriptionService::startTrial()`'s duplicate-trial check, `currentFor()`), the same established pattern as every other legitimate bypass.
- An initial `App\Support\UsageCounter::resetAll()` using Laravel's `Redis::connection()->scan()` wrapper silently found zero keys despite `dbsize()` confirming they existed — see Decision D-17.

### Known limitations, recorded rather than hidden
- Subscribe/upgrade/downgrade APIs, auto-renewal, dunning, grace-period automation, and expiry notifications are all unbuilt — every one is genuinely blocked on Phase 3.C's SSLCommerz integration (either to take a payment, or because "prorated credit" has no meaning without an invoicing ledger that also doesn't exist yet). `SubscriptionStatus::Grace` exists in the state machine; nothing transitions into or out of it yet.
- Resume/reactivate API: not blocked on anything (the transition graph already allows it), deferred only to keep this batch's scope bounded.
- `Coupon`/`FeatureFlag` UI, and Mission Control's own plan-editing screens, don't exist yet (Phase 13) — `feature_flags` and `plans` rows are currently only editable via seeders/tinker.

---

## Phase 2.C — RBAC & Audit / Phase 2.D — Frontend Auth & Team — 2026-08-17

### Added
- `config/permissions.php` — the module × action permission matrix (13 modules, 40 permissions) and Owner/Manager/Staff default grants; `RolePermissionSeeder` seeds it (idempotent)
- `App\Models\User` gains `HasRoles` (spatie/laravel-permission), `LogsActivity`; `App\Models\Tenant` gains `LogsActivity` — see §4.4.5
- `App\Policies\{TenantPolicy,UserPolicy,ActivityPolicy}`, auto-discovered; `AuthorizesRequests` added to the base `Controller`
- `staff_invitations` table/model/factory, `App\Enums\StaffInvitationStatus`, `App\Services\Staff\{StaffInvitationService,StaffService}`, `App\Notifications\StaffInvitationNotification`
- Staff CRUD + invite/accept/revoke API under `/api/v1/staff/*`, audit log API at `/api/v1/audit-log` — see §7.3a
- `App\Models\Activity` — replaces spatie/laravel-activitylog's own model, adds a `tenant_id` column (`BelongsToTenant`) so the audit log is tenant-isolated the same way every other model is
- `impersonations` table/model, `App\Services\Mission\ImpersonationService`, `POST /api/v1/mission/impersonate/{user}`, `POST /api/v1/auth/impersonation/exit` — see §7.15
- Decisions D-14 (global vs. per-tenant roles), D-15 (Mission Control stays enum-based, not spatie), D-16 (`ResolveTenant`'s authenticated-user fallback rewritten — see Fixed below)
- 31 new backend feature tests (`tests/Feature/Rbac/*`, 6 files) — 96 total, all passing
- Frontend (`apps/dashboard`): `RegisterPage`, `LoginPage`, `TwoFactorChallengePage`, `TwoFactorSetupPage`, `EmailVerificationNoticePage`, `ForgotPasswordPage`, `ResetPasswordPage`, `PendingApprovalPage`, `InviteAcceptPage`, `ProfileSettingsPage`, `SessionsPage`, `StaffListPage`, `AuditLogPage`, `ImpersonationBanner`, `ProtectedLayout`, `usePermissions`/`<Can>` — see PROGRESS.md Phase 2.D for the full list and §8.6
- `dashboardAuthStore` (roles/permissions/tenant-aware, distinct from `@fitmirror/api`'s generic `useAuthStore` — see its own docblock for why)
- 5 new frontend unit tests (`usePermissions.test.tsx`) and 1 new Playwright E2E test exercising a real register → login → RBAC-redirect flow against the live backend (`e2e/dashboard.spec.ts`) — 11/11 E2E tests passing across all four apps

### Fixed
- **`ResolveTenant`'s authenticated-user fallback never actually worked** for any Sanctum-token request — it called bare `$request->user()`, which resolves against the *default* guard (`web`, session-based, never authenticated on this API-only app) since this middleware runs before any route's own `auth:sanctum` middleware. Silently broken since Phase 2.A; invisible until Phase 2.C's staff/audit-log routes became the first to actually require it (`EnsureTenantIsActive` 404ing "Tenant not found" despite a valid Bearer token). See Decision D-16.
- The first fix attempt (`Auth::guard('sanctum')->user()`) "worked" in isolation but Laravel's `AuthManager` caches guard instances (and Sanctum's `RequestGuard` caches its resolved user) for the container's lifetime — a test making sequential requests as two different principals (`ImpersonationTest`) got the *first* request's cached principal on the second call. Replaced with a direct, stateless `PersonalAccessToken::findToken()` lookup that has no guard instance to cache.
- Password-reset emails linked to the bare backend `APP_URL` (Laravel's default `ResetPassword` notification builds a URL against a `password.reset` *web* route this API-only app never registers) — invisible until `ResetPasswordPage` needed a real link to land on. Fixed via `ResetPassword::createUrlUsing()` in `AppServiceProvider`.
- `RegisterPage` navigated to `/verify-email` after an unauthenticated registration (no token is issued at registration by design), but that route was nested under the authenticated `ProtectedLayout` — a fresh registrant would have been bounced straight to `/login` before ever seeing the verification notice. Caught by the new Playwright E2E test, not by inspection.
- `@testing-library/react`'s automatic per-test DOM cleanup never registered in any of the four apps' Vitest setup (`test.globals` isn't enabled, so the global `afterEach` RTL hooks into doesn't exist) — invisible until the first multi-test frontend file (`usePermissions.test.tsx`) accumulated every previous test's rendered DOM and failed with "multiple elements found". Fixed in all four apps' `test/setup.ts`.

### Known limitations, recorded rather than hidden
- Custom-role creation for Max-plan tenants is unbuilt — blocked on Phase 3.A's `plans` table (can't check "is this tenant on Max" without it).
- Policies exist only for `Tenant` and `User` — `Store`/`Product`/`Campaign`/`Loyalty`/`Customer`/`Report` don't exist as models until their own phases (4–10).
- Plan-based staff-account limits are unenforced — same `plans` blocker.
- Mission Control's own tenant-list/detail UI (the natural place to trigger impersonation from) is Phase 13 — the impersonation *backend* and the dashboard-side banner/exit flow are both fully built and tested (via a direct API call in `ImpersonationTest`), but nothing in Mission Control's UI calls that endpoint yet.

---

## Phase 2.A — Multi-Tenancy Core / Phase 2.B — Authentication — 2026-08-14

### Added
- `tenants` table/model/factory, `App\Enums\TenantStatus`, `App\Models\Concerns\BelongsToTenant`, `App\Scopes\TenantScope`, `App\Support\TenantContext`, `App\Http\Middleware\ResolveTenant`/`EnsureTenantIsActive`, `App\Support\TenantCacheKey`/`TenantStorage`, `App\Jobs\TenantAwareJob` — see §4.4.4 for the full isolation strategy
- Tenancy strategy write-up: DOCUMENTATION.md §4.4.4
- `users` table extended with `tenant_id`/`store_id`/`phone`/`avatar`/`locale`/`status`/`last_login_at`/2FA columns (migration, not a rewrite of the scaffold table); `App\Enums\UserStatus`
- Full tenant auth surface under `/api/v1/auth`: register, login (+ progressive lockout + 2FA challenge), logout, me, email verification (signed link + resend), forgot/reset/change password, profile update (incl. avatar upload), active session list/revoke, TOTP 2FA (enable/confirm/disable/recovery codes) — see §7.3 for the full endpoint reference
- `App\Services\Auth\{RegistrationService,LoginService,TwoFactorService}`, `App\Models\LoginAttempt` (+ `login_attempts` table), `App\Events\TenantRegistered`
- Shared password policy (`Password::defaults()`): min 10 chars, mixed case, number, symbol, Have I Been Pwned breach check — every password rule in the app inherits it from one place (`AppServiceProvider::configurePasswordPolicy()`)
- `App\Http\Middleware\EnsureTwoFactorIsEnabled` (alias `tenant.2fa`) — mandatory 2FA for tenant owners
- 65 backend feature tests total (was 8 at the end of Phase 1.C) — `tests/Feature/Auth/*` (46 tests), `tests/Feature/Tenancy/*` (12 tests, using a throwaway fixture model since no real tenant-owned business table exists until this phase)
- Decision D-13 recorded: `TenantScope` fails closed, and the three deliberate, audited bypasses that make authentication itself possible (`App\Models\PersonalAccessToken`, `App\Auth\TenantUnawareUserProvider`, `LoginService`'s own lookup) — see below

### Fixed
- **`TenantScope`'s fail-closed default silently broke every tenant login and every already-authenticated `auth:sanctum` request.** Caught by the Phase 2.B login/2FA feature tests, not by inspection: (1) `LoginService`'s own `User::where('email', ...)` lookup returned nothing, because no tenant context can exist yet at the exact moment login is what determines it; (2) Sanctum resolves a token's owner via a `MorphTo` relation that inherits the model's global scopes, so `$accessToken->tokenable` silently resolved to `null` for every tenant `User`, even with a perfectly valid token. Laravel's password-reset broker had the identical problem. Fixed with three narrow, deliberate bypasses — a custom Sanctum token model (`App\Models\PersonalAccessToken`), a custom `users` auth provider (`App\Auth\TenantUnawareUserProvider`), and `LoginService::attempt()`'s explicit `withoutTenantScope()` — see Decision D-13 for the full reasoning and why these three are the complete, intentional list.
- `Password::uncompromised()`'s Have I Been Pwned HTTP call wasn't reliably intercepted by a narrow `Http::fake(['api.pwnedpasswords.com/*' => ...])` pattern inside the full HTTP test-request cycle (worked fine called directly/via tinker) — broadened to `Http::fake(['*' => ...])` in the affected tests, which also guarantees zero real network calls leak out of the test suite.
- A Laravel testing artifact (not a production bug, first hit in Phase 1.C's `MissionLoginTest` — see that entry below): two sequential authenticated HTTP calls within one PHPUnit test method share a cached `AuthManager` guard instance. `LoginTest::test_logout_revokes_only_the_current_token` and equivalents needed no special handling here since each test issues a fresh token per call rather than reusing one across an assumed-stale state, unlike the Mission Control case.

### Known limitations, recorded rather than hidden
- Tenant provisioning (create tenant + owner + default roles + default store in one service) and teardown are still unbuilt — they depend on Phase 2.C roles and Phase 4.A stores. `RegistrationService` covers only what's real today: tenant + owner.
- Customer OTP authentication (Phase 2.B's last checklist item) is unbuilt — needs the `customers` table, which doesn't exist until Phase 9.A.
- Mandatory 2FA is enforced for tenant owners but not yet for super admins — Mission Control has no self-service 2FA setup flow of its own yet, so enforcing it now would be a lockout trap, not a security feature.

---

## Phase 1.B — Frontend Foundation (complete) / Phase 1.C — Mission Control Foundation — 2026-08-14

### Added
- `packages/ui`: `FileUploader` (drag-drop + client-side accept/size validation + per-item progress), `EmptyState`, `Skeleton`/`SkeletonCard`/`SkeletonTableRows`, `ErrorState`
- `packages/ui`: Recharts wrapper (`src/components/Chart.tsx`) — `TrendLineChart`, `TrendAreaChart`, `ComparisonBarChart`, all reading series colors from a new `chartPalette` token (`tokens/colors.ts`)
- `packages/ui/src/layouts`: `AppShell` (collapsible sidebar, breadcrumbs, user menu), `KioskShell` (full-screen, `user-select: none`, right-click disabled), `PortalShell` (mobile-first, centered on wide viewports, optional bottom tab bar), `MissionShell` (dark sidebar, deliberately distinct from `AppShell` so a screenshot can never be mistaken for the tenant dashboard)
- Sentry wired into all four apps: `src/lib/sentry.ts` (`initSentry()`, no-op without `VITE_SENTRY_DSN`) + `src/components/ErrorFallback.tsx` + `Sentry.ErrorBoundary` wrapping `<App />` in every `main.tsx`
- Vitest + React Testing Library: one `vitest.config.ts` per app, one passing `NotFound.test.tsx` per app; `packages/ui`, `packages/api`, `packages/i18n` each given a `tsconfig.json` (Decision D-11 — without one, Vitest's SSR module runner transformed `.tsx` with esbuild's classic JSX mode and threw `ReferenceError: React is not defined`, even though the real `vite build` was unaffected)
- Playwright E2E harness: root `playwright.config.ts` (4 projects, 4 `webServer` entries, one per app/port) + `e2e/*.spec.ts` — verified with real `npx playwright test` runs, not just written and assumed to work
- Backend: `super_admins` table/model/factory, `App\Enums\SuperAdminRole`/`SuperAdminPermission`/`SuperAdminStatus`, `super_admin` Sanctum guard (`config/auth.php`), `EnsureSuperAdmin` middleware, `routes/api_mission.php` (`/health`, `/login`, `/logout`, `/me`), `SuperAdminSeeder` (idempotent, `.env`-driven) — see §8.12 and §7.15
- Frontend: `apps/mission-control` real login page (`MissionLogin.tsx`), `useMissionAuthStore`, `routes/ProtectedLayout.tsx` (redirect-to-login guard) — verified against the live backend with a real `php artisan serve` + `vite` run, not only against mocks
- Decision D-11 (per-package `tsconfig.json` for correct Vitest JSX transform) and D-12 (`phpstan.neon` needs `parseModelCastsMethod: true` for Laravel 12's method-based `casts()`) recorded in PROGRESS.md

### Fixed
- Larastan silently typed every Eloquent cast attribute across the entire backend as plain `string` — including `datetime` casts — because `parseModelCastsMethod` (off by default) never saw any model's `casts()` method. Invisible until `SuperAdmin`'s enum casts produced real `identical.alwaysFalse` / `method.nonObject` false positives. See Decision D-12.
- A Laravel testing artifact, not a production bug: two sequential authenticated requests inside one PHPUnit test method shared a cached `AuthManager` guard instance, so a token deleted by the first request (logout) still appeared valid to the second (`/me`). Fixed test-side with `Auth::forgetGuards()` between calls — documented inline in `MissionLoginTest.php` so it isn't rediscovered from scratch next time.

### Resolved from the previous entry's "Known issues"
- The `FileUploader`/`EmptyState`/`Skeleton`/`ErrorState`/Recharts/`*Shell`/Sentry/Vitest/Playwright items deferred at the end of the 2026-08-01 Phase 1.B entry below are all now complete.

---

## Phase 1.B — Frontend Foundation (core) — 2026-08-01

### Added
- `frontend/` npm workspace root — 4 apps (`dashboard` :5173, `kiosk` :5174, `portal` :5175, `mission-control` :5176) + 4 packages (`ui`, `api`, `tryon`, `i18n`); Decision D-10 recorded (React 19.2 / react-router-dom v7, not React 18 / Router v6 as the product doc specifies — API-compatible, so the "v6 route trees" checklist language still holds)
- `packages/ui` design tokens (`colors`, `typography`, `spacing`/`radii`/`shadows`) and the Tailwind preset built from them (`tailwind-preset.ts`), extended by each app's own `tailwind.config.ts`
- `packages/ui` shared components: `Button`, `Input`, `Select`, `Checkbox`, `Radio`/`RadioGroup`, `Textarea`, `Modal`, `Drawer`, `Tooltip`, `Popover`, `Tabs`, `DataTable` (server-controlled sort/pagination/search, matching the `ApiResponse::paginated` envelope), `Toast`/`Toaster`
- `packages/ui` Zustand stores: `uiStore` (theme, sidebar, global loading), `toastStore` (imperative `toast.success/error/info/warning`)
- `packages/api` — `createApiClient()` (tenant header via `X-Tenant`, bearer auth interceptor, single-flight 401→refresh→retry with fallback to `onUnauthorized`), `createQueryClient()` (30s staleTime, no mutation retries), `authStore`, `tenantStore` (persisted), `tokenStorage`
- `packages/i18n` — `createI18n()` factory, `bn`/`en` `common` namespace resources (nav, actions, state, auth keys), locale persisted to `localStorage`
- `packages/tryon` — reserved package for Phase 6, not yet implemented
- Shared `tsconfig.base.json` (strict, `noUnusedLocals`, `exactOptionalPropertyTypes`, etc.) extended by every app
- Root `eslint.config.js` (flat config: typescript-eslint, react-hooks, react-refresh, simple-import-sort) and `.prettierrc.json` (with `prettier-plugin-tailwindcss`) — zero warnings across the workspace
- Route trees (`react-router-dom` declarative mode) for all four apps, each with a real home page and a 404 page; portal additionally has `/try-on/:token` (the Phase 6 QR-handoff landing route, fixed now so the URL shape is stable before the kiosk starts generating links against it)
- Production build config: content-hashed asset filenames (`assets/[name]-[hash].ext`) in every app's `vite.config.ts`
- `.env.example` per app (not one shared file) — `VITE_API_URL`/`VITE_MISSION_API_URL`, `VITE_SENTRY_DSN`, `VITE_REVERB_*`

### Changed
- §3.9, §4.3, §5.6 rewritten with the real frontend structure and commands (previously a target layout only)

### Known issues
- `react-router-dom@7.18.2` (latest on the npm registry as of this entry) carries GHSA-qwww-vcr4-c8h2, a high-severity RSC-mode CSRF advisory with no patched version yet published. Does not apply to FitMirror's usage (plain client-side `BrowserRouter`, no RSC/server actions) — tracked in Decision D-10, re-check `npm audit` before each release.
- Deferred to the next Phase 1.B batch: `FileUploader`, `EmptyState`/`Skeleton`/`ErrorState`, the Recharts wrapper, the four `*Shell` layouts, per-app `ErrorBoundary` + Sentry browser SDK, Vitest/Playwright harnesses.

---

## [0.1.0] — 2026-07-25

### Added
- Product document v2.0 reviewed and translated into an engineering plan
- Project documentation scaffolding established
