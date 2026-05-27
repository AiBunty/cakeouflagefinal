# SERVERBYT Deployment Master

Official SOP for all future Cakeouflage production deployments to Serverbyt / StackCP FTP hosting.

## Core Rule

Local workspace is the master source of truth for code.

Production must be aligned to local code without breaking:

- orders
- OTP login
- session persistence
- checkout
- production planning
- admin CRM

## Never Do This

- Never overwrite production `.env`
- Never upload local `.env.production` as production `.env`
- Never delete `uploads`, `public/uploads`, `storage/logs`, `storage/sessions`, or runtime-generated files
- Never run destructive SQL (`DROP`, destructive `ALTER`, reset IDs, purge data)
- Never assume production DB defaults match local defaults
- Never deploy before auditing FTP, DB, runtime, and auth/session behavior

## Always Do This

- Audit live first
- Compare local vs production constraints
- Generate additive SQL only
- Upload only eligible changed files
- Preserve runtime directories and uploaded media
- Validate auth/session immediately after deploy
- Run deeper order-flow tests only with explicit intent because they create live test artifacts

## Current Production Baseline

As of 2026-05-26:

- Runtime: Apache + PHP-FPM
- PHP: 8.2.31
- DB: MariaDB 10.6.18
- DB hostname in live runtime: internal StackCP host
- FTP host: StackCP FTP root serving the live site
- Document root: `/home/sites/14b/b/bf69cff851/public_html/cakeouflage.com/`
- Writable runtime dirs: `storage`, `storage/sessions`, `storage/logs`, `uploads`, `public/uploads`
- Live upload limits: `100M` / `100M`

See [docs/production-server-audit.md](docs/production-server-audit.md) for the full audited snapshot.

## Local Environment Requirements

- PHP CLI available locally
- Docker available locally for MariaDB client access
- `.env.production` present locally for FTP and production DB reference
- Do not treat `.env.production` as the file to upload to production
- Local `.htaccess` and `.user.ini` should remain source-of-truth files for runtime behavior

## Compatibility Rules

- Code must remain parse-safe on local PHP 8.1 and production PHP 8.2
- New DB objects must declare charset/collation explicitly
- Additive migrations only
- Do not rely on production DB server defaults (`latin1`)
- Preserve existing live auth/session behavior unless a separate auth hardening task is approved

## Files and Folders To Exclude From FTP Deploy

- `.git/`
- `.gitignore`
- `.env`
- `.env.production`
- `.env.local*`
- `node_modules/`
- `storage/logs/`
- `storage/cache/`
- `storage/sessions/`
- `storage/backups/`
- `uploads/`
- `public/uploads/`
- `admin/backups/`
- `HOTFIX_DEPLOYMENT_BUNDLE/`
- local Docker files when not explicitly required:
  - `docker-compose.yml`
  - `Dockerfile`
  - `Dockerfile.txt`
- temporary or local-only artifacts:
  - `.vscode/`
  - `.sixth/`
  - `*.7z`

## Standard Deployment Flow

1. Audit production.
2. Confirm local-vs-production compatibility.
3. Generate `database/migrations/serverbyt_sync.sql`.
4. Run `deploy-serverbyt.ps1` in audit mode / dry-run.
5. Run actual FTP upload.
6. Run additive DB sync.
7. Run post-deploy validation.
8. Record outcomes in docs or release notes.

## PowerShell Commands

Dry-run / audit upload plan:

```powershell
pwsh -File .\deploy-serverbyt.ps1 -WhatIf
```

Actual file upload only:

```powershell
pwsh -File .\deploy-serverbyt.ps1
```

Actual file upload plus DB sync:

```powershell
pwsh -File .\deploy-serverbyt.ps1 -RunMigration
```

Upload, DB sync, and safe validation:

```powershell
pwsh -File .\deploy-serverbyt.ps1 -RunMigration -RunValidation
```

## Database Migration Process

- Migration file: `database/migrations/serverbyt_sync.sql`
- SQL must be additive only
- Current generated delta adds three missing local-master tables:
  - `crm_customer_tags`
  - `order_production_plan`
  - `order_production_audit_logs`

If migration fails:

- Stop immediately
- Do not continue validation as if deploy succeeded
- Inspect SQL error output
- Do not patch live DB manually without documenting exact change

## Post-Deployment Validation Checklist

### Baseline HTTP

- Homepage returns `200`
- `/account` returns `200`
- `/admin/login.php` returns `200`
- `/api/health/db` returns success JSON

### Auth / Session

- Customer OTP send works
- Customer OTP verify works
- Authenticated `/api/auth/me` works
- Logout invalidates session
- Admin OTP login still lands on dashboard

### Admin

- Dashboard loads
- Orders loads
- CRM settings and CRM diagnostics load
- Reports load

### Database

- No migration errors
- No duplicate-key or duplicate-column errors
- No FK errors
- Health endpoint remains green

### Deeper Live Order Validation

Run only when explicitly needed because it creates live test records.

- Manual order flow
- Online order flow
- BYOC inquiry/order flow
- Production planning checks
- CRM history checks

Preferred operator tool for this is the existing `scripts/qa/live_safe_e2e_runner.ps1` with documented cleanup expectations.

## Common Failure Scenarios

### Production `.env` drift

Symptom:

- DB host or session behavior changes unexpectedly after deploy

Cause:

- Someone uploaded or replaced production `.env`

Action:

- Restore the live `.env` immediately from hosting backup or known-good copy

### Charset mismatch

Symptom:

- New tables come up with wrong charset/collation

Cause:

- SQL relied on production DB server defaults

Action:

- Regenerate migration with explicit `CHARSET` / `COLLATE`

### Session break

Symptom:

- OTP verify works but session does not persist after redirect

Check:

- `storage/sessions` still exists and remains writable
- session path logic was not overwritten incorrectly
- production `.env` cookie settings were preserved

### Media / upload failure

Symptom:

- uploads fail after deploy

Check:

- `uploads` and `public/uploads` were not overwritten or permission-damaged
- `.user.ini` still matches expected limits

## Rollback Guidance

- Roll back code only, not data.
- Use the FTP upload log to identify changed files.
- Re-upload the previous known-good code snapshot.
- Do not roll back by deleting runtime directories.
- Do not delete orders, sessions, payment proofs, or uploads.
- If DB migration already ran successfully, do not attempt destructive DB rollback unless a separately approved DBA plan exists.

## Session Requirements

- Production customer-facing app relies on `cakeouflage_sid`
- Runtime storage must preserve `storage/sessions`
- Customer-facing routes use secure cookie settings on live
- Admin login flow currently has its own bootstrap path and must be preserved as-is during code mirror deployment

## Charset Requirements

- All new schema objects must explicitly declare `utf8mb4`
- Do not rely on production DB default `latin1`
- Existing production-only data must remain untouched

## Production-Safe Rules

- Local code is the master source for deployable code.
- Production `.env` is the master source for production secrets/runtime wiring.
- Runtime content directories are never mirrored from local.
- Schema alignment is additive only.
- Validation must happen immediately after upload.

## Permanent Deployment Architecture

This repository now uses a permanent deployment platform layout:

- `deploy/deploy-serverbyt.ps1` (main release deploy script)
- `deploy/deploy-hotfix.ps1` (targeted hotfix deploy)
- `deploy/deploy-validate.ps1` (audit and post-deploy validation engine)
- `deploy/runtime/Deploy.Common.ps1` (shared lock, history, FTP, DB, and Git helpers)
- `deploy/winscp-sync.txt` (WinSCP sync template)
- `deploy/excludes.txt` (authoritative exclusion list)
- `deploy/logs/` (deployment and validation logs)
- `storage/deployment/deployment-lock.json` (single active deployment lock)
- `storage/deployment/deployment-history.json` (append-only deployment ledger)

Supporting directories for lifecycle evidence and schema governance:

- `database/migrations/`
- `database/snapshots/`
- `database/schema/`

## Deployment Modes

### Standard Release

Use for planned deployments.

- `pwsh -File ./deploy/deploy-serverbyt.ps1`
- Optional migration: `-RunMigration`
- Optional post-validation: `-RunValidation`

### Dry Run

Use for safe planning without file write operations.

- `pwsh -File ./deploy/deploy-serverbyt.ps1 -WhatIf`

### Hotfix Release

Use for urgent file-targeted corrections only.

- `pwsh -File ./deploy/deploy-hotfix.ps1 -Files <path1>,<path2> -RunValidation`

Hotfix hard blocks:

- `.env` / `.env.production`
- `uploads/`
- `public/uploads/`

## Validation Engine

`deploy/deploy-validate.ps1` is the audit gate for both pre- and post-deployment checks.

Checks include:

- FTP connectivity and runtime writable directories
- Production DB connectivity and runtime metadata capture
- HTTP status checks for critical routes
- Optional auth/session verification using `scripts/qa/live_auth_verify.ps1`

Deploy scripts must fail fast if validation gate fails.

## Locking and History Rules

- Exactly one deployment operation can run at a time.
- Lock file is acquired at start and released in `finally` block.
- Every run appends a history entry with:
  - release id
  - operation type
  - actor/host
  - Git branch/commit/dirty state
  - migration and validation flags
  - success/failure and error message
  - uploaded file list metadata
  - log path

## Git Release Metadata

Every deployment captures Git metadata to ensure traceable releases:

- branch
- short commit
- dirty working tree state
- generated release id

If `git` is unavailable, deployment still runs but stores `unknown` metadata values.

## VS Code Task Operations

The deployment workflow is standardized via `.vscode/tasks.json`:

- `Audit Production`
- `Dry Run Deployment`
- `Deploy Files Only`
- `Deploy + Migration`
- `Hotfix Deploy`
- `Post Deploy Validation`

Operators should use tasks by default to reduce command drift.

## Operational References

- Checklist: `docs/deployment-checklist.md`
- Rollback procedure: `docs/rollback-guide.md`

These references are mandatory for release and incident handling.
