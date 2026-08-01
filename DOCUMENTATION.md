# FitMirror — Technical Documentation

> **Living document.** Every section is updated as the corresponding part of the system is built.
> Sections marked _"To be filled as we build"_ contain the final structure and are populated with real content (real commands, real endpoints, real schema) the moment the related task in `PROGRESS.md` is completed. No placeholders are left behind.

**Version:** 0.3.0 (Phase 1 backend + frontend foundation core complete)
**Last updated:** 2026-08-01
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
├── lib/apiClient.ts   createApiClient(...) instantiated with the app's own VITE_API_URL
├── pages/             route-level components
├── routes/index.tsx   <AppRoutes /> — the app's <Routes> tree (react-router-dom v7,
│                       declarative mode — BrowserRouter/Routes/Route, API-
│                       compatible with v6 usage)
├── App.tsx            wires I18nextProvider → QueryClientProvider → BrowserRouter
│                       → AppRoutes + <Toaster />
├── main.tsx
├── index.css           @tailwind directives; each app's tailwind.config.ts
│                        extends @fitmirror/ui/tailwind-preset
└── vite-env.d.ts        ImportMetaEnv typing for this app's VITE_* variables
```

Deferred to a later Phase 1.B batch: `FileUploader`/`EmptyState`/`Skeleton`/`ErrorState`
in `packages/ui`, the Recharts wrapper, the four `*Shell` layouts (`AppShell`/
`KioskShell`/`PortalShell`/`MissionShell`), per-app Sentry + `ErrorBoundary`, and the
Vitest/Playwright harnesses. The legacy internal layout below (`components/`,
`features/`, `hooks/`, `layouts/`, `stores/`) is created per app as each of those
lands — not scaffolded speculatively ahead of the code that needs it.

```
apps/<app>/src/ (grows to, as pages are built)
├── components/    app-specific components
├── features/      feature folders (products, campaigns, loyalty, ...)
├── hooks/
├── layouts/
├── lib/
├── pages/         route components
├── routes/        router configuration
├── stores/        zustand stores
└── main.tsx
```

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
| `SSLC_SUCCESS_URL` | Success callback | `${APP_URL}/payment/success` | Yes |
| `SSLC_FAIL_URL` | Failure callback | `${APP_URL}/payment/fail` | Yes |
| `SSLC_CANCEL_URL` | Cancel callback | `${APP_URL}/payment/cancel` | Yes |
| `SSLC_IPN_URL` | IPN webhook | `${APP_URL}/webhooks/sslcommerz` | Yes |
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
| `SUPER_ADMIN_EMAIL` | Seeded super admin email | `owner@fitmirror.com` | Yes |
| `SUPER_ADMIN_PASSWORD` | Seeded super admin password (change after first login) | `...` | Yes |

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
| Plans & Billing | `plans`, `plan_limits`, `plan_features`, `feature_flags`, `subscriptions`, `invoices`, `invoice_items`, `payments`, `coupons`, `coupon_redemptions`, `addons`, `tenant_addons` |
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

_Empty — populated per migration._

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
_Empty — populated in Phase 2._

## 7.4 Tenant & Profile Endpoints
_Empty — populated in Phase 2._

## 7.5 Subscription & Billing Endpoints
_Empty — populated in Phase 3._

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
_Empty — populated in Phase 13._

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

_To be filled in Phase 4._

## 8.7 Analytics & BI

**Purpose:** Turn try-on behaviour into buying and staffing decisions.

**Feature scope:** try-on heatmap, conversion funnel, peak hours, category performance, customer demographics, revenue reports with YoY, campaign ROI, loyalty analytics, dead stock alerts, retention rate, wishlist trend, PDF/Excel export.

_To be filled in Phase 10._

## 8.8 Subscription & Billing

**Purpose:** Monetization through SSLCommerz with owner-controlled activation.

**Feature scope:** SSLCommerz integration (bKash, Nagad, Rocket, VISA, MasterCard, DBBL), manual owner approval, auto renewal with grace period, prorated billing, branded PDF invoices, configurable trial, coupons, dunning retries on days 1/3/7, offline payment confirmation, VAT invoices, refund management.

_To be filled in Phase 3._

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

_To be filled in Phase 14._

## 8.12 Mission Control

**Purpose:** The Product Owner's single control centre for the whole platform.

**Feature scope:** tenant management with approval/rejection and impersonation, plan and pricing control with feature flags, revenue dashboard (MRR, ARR, churn, ARPU), platform operations (maintenance mode, announcements, email blasts, system health, backups), support tickets and in-platform messaging, full audit log.

_To be filled in Phase 13._

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
