# Local MySQL 8 Alignment + FTP Deploy Manifest

Generated: 2026-05-22

## Local Alignment Status

- Local DB engine switched to MySQL 8 via `docker-compose.yml`
- Local stack rebuilt from fresh volume (`docker compose down -v` then `up -d`)
- App DB integrity checks passed using app credentials:
  - tables: 16
  - foreign keys: 17
  - indexes: 57
  - server charset/collation: utf8mb4 / utf8mb4_unicode_ci

## Exact File Changed For MySQL 8 Migration

- `docker-compose.yml`

## Local Cleanup Completed

Deleted as requested:

- `backups/`
- temp deploy extracts: `.tmp_deploy_extract`, `.tmp_deploy_extract2`, `.tmp_upload_extract`
- temp deployment scripts: `.tmp_*.ps1`, `.tmp_drop_all_tables.sql`
- deploy backup helpers: `scripts/deploy/phase1_live_backup.ps1`, `scripts/deploy/validate-production-env.ps1`
- stale local temp/debug dirs: `tmp/`, `tools/`
- stale cache/log/import logs in `storage/` (kept `.gitkeep`)

## Exact DB Dump To Import On Server

- `database/dumps/cakeouflage_local_mysql8_20260522_074211.sql`

## FTP Deploy Source (Local Master)

Use workspace root as source and preserve relative paths.

### Upload These Root Files

- `.htaccess`
- `.user.ini`
- `index.php`
- `config.php`
- `composer.json`
- `composer.lock`
- `.env.production` (upload and rename to `.env` on server)

### Upload These Folders Recursively

- `admin/`
- `app/`
- `client/`
- `database/`
- `public/`
- `uploads/`
- `vendor/`
- `storage/` (only structure/placeholders needed by app)

## Exclude From FTP Upload

- `.git/`
- `.docker/`
- `.sixth/`
- `docs/`
- `scripts/`
- `docker-compose.yml`
- `Dockerfile`
- `.env` (never upload local `.env`)
- `.env.example`
- `.env.local.production.example`

## Safe Overwrite Scope on Server

Safe to fully overwrite with local master:

- `admin/`, `app/`, `client/`, `database/`, `public/`, `vendor/`
- root files listed above

Also overwrite `uploads/` only if local uploads are intentionally the source of truth.

## Validation Notes

- Route checks passed locally: `/`, `/shop`, `/cart`, `/checkout`, `/login`, `/register`, `/admin/login`
- `/api/health` currently returns 500 due pre-existing app callback issue (`ApiController::health` missing), not due MySQL migration
- OTP endpoint reachable but currently returns `Invalid CSRF token` when called without frontend session/CSRF context
