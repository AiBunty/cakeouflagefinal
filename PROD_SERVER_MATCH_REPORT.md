# PROD_SERVER_MATCH_REPORT

Date: 2026-05-26
Objective: Compare local canonical mirror against declared production requirements and identify drift/blockers before Phase 2+.

## Master Rule Applied
No live patching performed in this phase. Only local hardening + parity verification.

## Comparison Matrix

| Requirement | Local Mirror (Docker) | Production Target Source | Status | Notes / Action |
|---|---|---|---|---|
| PHP version | 8.2.31 | `.htaccess` handler `php82` + prod env conventions | MATCH | Major/minor aligned to PHP 8.2 runtime intent. |
| MariaDB version | 10.6.25 | Shared-host MariaDB target (declared stack) | PARTIAL | Exact live version not directly queried from host panel/API in this phase. |
| Apache modules | rewrite/headers/expires/php present | [Dockerfile](Dockerfile), [.htaccess](.htaccess) routing assumptions | PARTIAL | Local module set validated; live module list not directly exported. |
| File permissions | storage/uploads writable | Shared hosting requires writable storage/uploads | MATCH | Local writable paths verified in container. |
| FFmpeg availability | Present (7.1.4) | Required by media pipeline | PARTIAL | Local passes; live FFmpeg binary presence still needs host confirmation snapshot. |
| Session settings | `cakeouflage_sid`, `SameSite=Lax`, env-aware secure/lifetime | [.env.production](.env.production) + [app/bootstrap.php](app/bootstrap.php) | MATCH | Behavior validated for local HTTP and tokenized app session flow. |
| Upload limits | 100M/100M, memory 1024M, max input/exe 600 | [.user.ini](.user.ini) + [.docker/php/php-production.ini](.docker/php/php-production.ini) | MATCH | Local mirror aligned to declared production limits. |
| SSL behavior | Local HTTP uses non-secure cookie; secure on HTTPS path | `.env.production` (`SESSION_COOKIE_SECURE=1`) + bootstrap HTTPS detection | PARTIAL | Logic is correct; local TLS endpoint not yet provisioned for full secure-cookie E2E. |
| Cron behavior | Queue endpoint enforces token; authorized processing succeeds | [app/Controllers/CronController.php](app/Controllers/CronController.php) + env token | MATCH | 401 without token and 200 with valid token confirmed locally. |
| Queue behavior | Queue process endpoint executes and returns success payload | Queue worker + cron process | MATCH | Basic execution validated (max_jobs probe). |
| SQL mode / strict mode | Strict mode enabled (`STRICT_TRANS_TABLES...`) | MariaDB strict requirement | MATCH | Local DB strict mode active. |
| Timezone | PHP `Asia/Kolkata`; DB system `IST` | India timezone expectation in app + operations | MATCH | Hardening patch applied to PHP config. |

## Drift and Blockers

### A. Blockers for Full 1:1 Certification (Not code defects)
1. Live hosting introspection missing in this phase:
- Exact live PHP patch version
- Exact live MariaDB patch version and full sql_mode
- Live Apache module dump
- Live FFmpeg binary presence/version

2. Local TLS endpoint not yet enabled:
- Secure-cookie E2E over HTTPS not fully replayed locally.

### B. Resolved Drift in this phase
1. `APP_DEBUG` drift fixed locally:
- [docker-compose.yml](docker-compose.yml) now uses `APP_DEBUG=false`.

2. PHP timezone drift fixed locally:
- [.docker/php/php-production.ini](.docker/php/php-production.ini) now includes `date.timezone=Asia/Kolkata`.

## Required Next Actions Before Deployment Gate
1. Capture live runtime snapshots (panel export or SSH equivalent):
- `php -v` / phpinfo subset
- MariaDB `SELECT @@version, @@sql_mode, @@time_zone, @@system_time_zone`
- Apache module list
- `ffmpeg -version`

2. Add local HTTPS test endpoint (mkcert/nginx-proxy or equivalent) and validate:
- Secure cookie presence over HTTPS
- OTP login persistence under secure-cookie mode

3. Proceed to Phase 2 only after recording above as evidence artifacts.

## Phase 1 Decision
- Phase 1 status: IMPLEMENTED
- Mirror status: STABLE BASELINE CREATED
- Certification status: CONDITIONAL PASS (pending live runtime evidence capture and local HTTPS replay)
