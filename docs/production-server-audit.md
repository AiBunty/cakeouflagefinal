# Production Server Audit

Date: 2026-05-26
Scope: Live production audit before any mirror deployment from local master source.
Application: https://cakeouflage.com

## Audit Sources

- Local reference config: `.env.production`
- Live HTTP headers from `https://cakeouflage.com/`, `/account`, `/admin/login.php`, `/api/health/db`
- Live diagnostics already present on server:
  - `/db-runtime-diagnostic.php`
  - `/pdo-runtime-diagnostic.php`
  - `/api/health/db`
- Live OTP/session probe: `scripts/qa/live_auth_verify.ps1`
- FTP directory and write/delete tests using credentials from `.env.production`
- Direct production DB metadata via PDO using `.env.production`
- Temporary runtime probe uploaded and deleted immediately after capture

## Executive Summary

Production is reachable and stable enough for controlled FTP deployment, but there are important constraints that must be preserved:

- Do not overwrite production `.env`.
- Do not overwrite `uploads`, `public/uploads`, `storage/logs`, `storage/sessions`, or runtime-generated files.
- Do not run destructive schema changes.
- Production runtime differs from local in important ways:
  - Production PHP is 8.2.31 via Apache + PHP-FPM.
  - Local PHP is 8.1.34 CLI.
  - Production DB server defaults are `latin1` / `latin1_swedish_ci` and looser SQL mode.
  - Local DB defaults are `utf8mb4` / `utf8mb4_unicode_ci` and stricter SQL mode.
- Existing application-level session handling is functional on live and must be preserved.

## 1. FTP Connectivity

Status: PASS

Verified with passive FTP mode using `.env.production` credentials.

- Login: PASS
- Directory listing: PASS
- Upload/delete test: PASS

Writable production paths verified:

- `/storage`
- `/uploads`
- `/public/uploads`

Write/delete probe result:

- Upload: `226 Transfer complete`
- Delete: `250 DELE command successful`

## 2. Server Directory Structure

Status: PASS

Observed FTP root aligns with site document root deployment layout.

Top-level live directories/files observed:

- `admin/`
- `app/`
- `client/`
- `config/`
- `database/`
- `docs/`
- `public/`
- `scripts/`
- `storage/`
- `uploads/`
- `vendor/`
- root PHP entry files such as `index.php`, `media.php`, `order.php`

Writable runtime directories observed:

- `storage/cache`
- `storage/import-logs`
- `storage/logs`
- `storage/sessions`
- `uploads/media`
- `uploads/products`
- `public/uploads/*`

## 3. PHP Version

Status: PASS

Verified live values:

- PHP version: `8.2.31`
- SAPI: `fpm-fcgi`
- `X-Powered-By`: `PHP/8.2.31`

## 4. MariaDB Version

Status: PASS

Verified live values:

- DB server version: `10.6.18-MariaDB-log`
- Version comment: `MariaDB Server`
- Hostname: `shareddb36.lhr.stackcp.net`

Important note:

- Local `.env.production` points to the external remote endpoint `mysql.gb.stackcp.com:44087`.
- Live runtime diagnostics show the production app itself uses an internal StackCP DB host (`sdb-62.hosting.stackcp.net:3306`).
- This is exactly why production `.env` must never be overwritten.

## 5. Apache / Nginx Behavior

Status: PASS

Verified live values:

- Server: `Apache`
- SAPI: `fpm-fcgi`
- CDN layer present: `StackCDN`

Observed headers include:

- `server: Apache`
- `x-provided-by: StackCDN`
- `x-via: SIN1`

## 6. Rewrite Rules

Status: PASS

Verified with live routes:

- `/` => `200`
- `/account` => `200`
- `/api/health/db` => `200`
- `/admin/login.php` => `200`

Local `.htaccess` is production-critical and currently matches the live routing model:

- Forces PHP 8.2 handler
- Canonicalizes `www` to bare domain
- Canonicalizes legacy subfolder paths to root
- Uses front-controller rewrite for clean routes
- Keeps `/shop` routed through `index.php`

## 7. Session Handling

Status: PASS WITH IMPORTANT NUANCE

### Raw PHP runtime defaults from temporary probe

- Default session name: `PHPSESSID`
- Default session save path: `/tmp`
- Default `session.cookie_secure`: `0`
- Default `session.cookie_httponly`: empty
- Default `session.cookie_samesite`: empty

### Verified application-level behavior on live

Customer/auth routes are overriding runtime defaults successfully:

- Session cookie name observed live: `cakeouflage_sid`
- Cookie lifetime observed on customer-facing routes: `7200` seconds
- `Secure`: present on customer-facing routes
- `HttpOnly`: present on customer-facing routes
- `SameSite=Lax`: present on customer-facing routes
- OTP login/session persistence verified with `scripts/qa/live_auth_verify.ps1`
- Post-login `/api/auth/me` returned authenticated user
- Post-logout `/api/auth/me` returned unauthenticated response

### Existing auth/session behavior finding

The admin login page currently starts its session before applying the same cookie parameter hardening used in `app/bootstrap.php`, so the initial `Set-Cookie` on `/admin/login.php` is less strict than the customer-facing app bootstrap cookie.

This is an existing live behavior. It is functional and should not be altered as part of this deployment unless explicitly scheduled as a separate auth hardening task.

## 8. File Permissions

Status: PASS

Observed live permissions through FTP listings:

- Directories: typically `0755`
- Files: typically `0644`

Temporary runtime probe confirmed writable and existing runtime directories:

- `storage` => writable, `0755`
- `storage/sessions` => writable, `0755`
- `storage/logs` => writable, `0755`
- `uploads` => writable, `0755`
- `public/uploads` => writable, `0755`

## 9. Upload Limits

Status: PASS

Verified live runtime values from temporary probe:

- `upload_max_filesize = 100M`
- `post_max_size = 100M`
- `max_file_uploads = 10`
- `memory_limit = 1024M`
- `max_execution_time = 300`

Local app config reinforces a production media policy cap of 100 MB in `admin/banners.php`.

## 10. Installed PHP Extensions

Status: PASS

Verified live extensions include at least:

- `mysqli`
- `pdo_mysql`
- `curl`
- `mbstring`
- `intl`
- `gd`
- `imagick`
- `openssl`
- `fileinfo`
- `ftp`
- `zip`
- `json`
- `session`
- `Zend OPcache`

Additional live extensions observed include `soap`, `ldap`, `imap`, `pgsql`, `sqlsrv`, `sqlite3`, `sockets`, `xsl`, and others.

`disable_functions` is empty on live runtime probe.

## 11. Charset / Collation

Status: PASS WITH COMPATIBILITY WARNING

Production DB server defaults:

- Server charset: `latin1`
- Server collation: `latin1_swedish_ci`
- Schema default charset: `latin1`
- Schema default collation: `latin1_swedish_ci`

Important nuance:

- Key application tables are already created with explicit `utf8mb4` collations.
- New deployment SQL must continue to specify charset/collation explicitly and must not rely on server defaults.

## 12. Timezone

Status: PASS WITH COMPATIBILITY WARNING

Production runtime probe:

- PHP default timezone: `Europe/London`

Production DB values:

- `@@time_zone = SYSTEM`
- `@@system_time_zone = GMT`

Application note:

- Local code explicitly sets `date_default_timezone_set('Asia/Kolkata')` in `app/bootstrap.php`.
- Any production behavior that bypasses bootstrap may still inherit the server default timezone.

## 13. Error Reporting Settings

Status: PASS

Runtime probe values:

- `display_errors`: off/empty
- `log_errors`: `1`
- `error_reporting`: `22519`

This is production-safe and should remain that way.

## 14. Existing DB Schema Structure

Status: PASS

Production DB summary:

- Table count: `97`
- All tables use `InnoDB`

Production is only missing three local-master tables:

- `crm_customer_tags`
- `order_production_plan`
- `order_production_audit_logs`

No additive drift was detected on already-shared tables. The schema delta is table-level only.

## 15. Existing Production Table Engine Types

Status: PASS

- `InnoDB = 97`
- No mixed-engine issues detected in current production metadata sample.

## 16. Existing Indexes

Status: PASS

Verified key live indexes exist on core tables including:

- `orders`
- `users`
- `otp_verifications`
- `queue_jobs`
- `order_slots`
- `order_slot_exceptions`
- `collection_followup_logs`

Missing production indexes are only the indexes that belong to the three production-missing tables listed above.

## 17. Existing Auth / Session Behavior

Status: PASS

Verified live behavior:

- Customer OTP send: PASS
- Customer OTP verify: PASS
- Customer session persistence: PASS
- Customer logout invalidation: PASS
- Admin OTP flow is functional in live browser validation performed earlier in session
- Session storage path used by application code is `storage/sessions`

## Local vs Production Constraint Comparison

| Area | Local | Production | Deployment Rule |
| --- | --- | --- | --- |
| PHP | 8.1.34 CLI | 8.2.31 FPM | Keep code parse-safe for 8.1+, deploy to 8.2 |
| DB version | 10.6.25 | 10.6.18 | Use additive SQL only |
| DB defaults | `utf8mb4` / `utf8mb4_unicode_ci` | `latin1` / `latin1_swedish_ci` | Always declare charset/collation explicitly |
| SQL mode | stricter local mode | looser production mode | Avoid destructive or fragile SQL |
| Session cookie | insecure locally on HTTP | secure on live customer routes | Never overwrite production env/session settings |
| Rewrite | root `.htaccess` | Apache root rewrite works | Upload `.htaccess` when changed |
| Upload limits | `.user.ini` 100M | verified 100M live | Preserve `.user.ini` |
| Runtime storage | local mounted dirs | live writable `storage/*`, `uploads/*` | Never overwrite runtime content |

## Gate Decision

Gate status: GO, with strict safety rules.

Deployment may proceed only if all of the following are respected:

- Exclude `.env`, `.env.production`, `.env.local*`
- Exclude runtime directories and uploaded media
- Run only additive DB sync SQL
- Keep `.htaccess` and `.user.ini` in sync with local master source
- Validate auth/session after deployment
