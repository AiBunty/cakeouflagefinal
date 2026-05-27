# LOCAL_SERVER_AUDIT

Date: 2026-05-26
Scope: Phase 1 local hardening and runtime audit for production mirror readiness.

## 1. Runtime Topology
- Web runtime: Docker container `cakeouflage-web` built from [Dockerfile](Dockerfile).
- DB runtime: Docker container `cakeouflage-db` using `mariadb:10.6` from [docker-compose.yml](docker-compose.yml).
- Host CLI runtime (non-canonical for mirror): PHP 8.1.34 on Windows.

## 2. Local Canonical Mirror Facts (Container)

### PHP
- Version: 8.2.31
- SAPI (audit command context): CLI in container
- Loaded config includes [.docker/php/php-production.ini](.docker/php/php-production.ini)
- Key settings (verified):
  - `memory_limit=1024M`
  - `post_max_size=100M`
  - `upload_max_filesize=100M`
  - `date.timezone=Asia/Kolkata`
  - `session.cookie_samesite=Lax`
  - `session.cookie_secure=Off`

### Apache modules (container)
Verified modules include:
- `rewrite_module`
- `headers_module`
- `expires_module`
- `php_module`
- `mpm_prefork_module`

### MariaDB
- Version: 10.6.25-MariaDB-ubu2204
- SQL mode:
  - `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`
- Timezone:
  - Session: `SYSTEM`
  - System: `IST`

### Media / FFmpeg
- FFmpeg present in web container.
- Version: 7.1.4

### File permissions (container)
- `/var/www/html/storage` => writable (`drwxrwxrwx`)
- `/var/www/html/storage/sessions` => owned by `www-data`, writable to service user
- `/var/www/html/public/uploads` => writable (`drwxrwxrwx`)
- `/var/www/html/uploads` => writable (`drwxrwxrwx`)

## 3. App Session and Cookie Behavior
- Session bootstrap in [app/bootstrap.php](app/bootstrap.php):
  - Session name: `cakeouflage_sid`
  - Cookie params are env/request aware (`secure`, `samesite`, `lifetime`, optional domain)
  - HTTPS detection considers `HTTPS`, `SERVER_PORT=443`, and `X-Forwarded-Proto`
- HTTP header probe for local `http://localhost:8080/account` returned:
  - `Set-Cookie: cakeouflage_sid=...; Max-Age=7200; path=/; HttpOnly; SameSite=Lax`
  - `Secure` attribute is absent on HTTP, as expected for local non-TLS mode.

## 4. Queue and Cron Behavior
- Unauthorized queue call:
  - `GET /cron/queue/process` => `401` (token required)
- Authorized queue call:
  - `GET /cron/queue/process?token=<valid>&max_jobs=1` => `200` with success JSON

## 5. DB Connectivity Diagnostics
- Host-side diagnostics from Windows CLI fail against `db` alias (expected due container DNS boundary).
- In-container diagnostics succeed for configured `db:3306` and `cakeouflage_local`.

## 6. Local Hardening Changes Applied
1. [docker-compose.yml](docker-compose.yml)
- Changed `APP_DEBUG` from `"true"` to `"false"` for production-like behavior.

2. [.docker/php/php-production.ini](.docker/php/php-production.ini)
- Added `date.timezone=Asia/Kolkata` for timezone parity.

## 7. Local Audit Verdict
- Status: PASS WITH KNOWN LIMITATIONS
- Canonical local mirror (Docker) is operational and aligned on core runtime stack.
- Remaining parity gaps are documented in [PROD_SERVER_MATCH_REPORT.md](PROD_SERVER_MATCH_REPORT.md).
