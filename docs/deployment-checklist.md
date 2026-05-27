# Deployment Checklist

This checklist is the operational gate for all production releases.

## Absolute Safety Rules

- DO NOT overwrite production .env
- DO NOT overwrite uploads/
- DO NOT overwrite public/uploads/
- DO NOT run destructive SQL
- DO NOT deploy without validation/audit gates

## Pre-Deploy

- Confirm latest local code is committed or intentionally tracked as a release candidate.
- Confirm `.env.production` exists locally and is not staged for upload.
- Run `Audit Production` task.
- Confirm FTP connectivity and writable runtime paths are PASS.
- Confirm DB connectivity and `/api/health/db` are PASS.
- Confirm no active deployment lock in `storage/deployment/deployment-lock.json`.

## Dry Run

- Run `Dry Run Deployment` task.
- Confirm no forbidden paths are included by excludes:
  - `.env*`
  - `uploads/`
  - `public/uploads/`
  - `storage/logs/`
  - `storage/sessions/`

## Deploy

- Choose one deployment mode:
  - `Deploy Files Only`
  - `Deploy + Migration`
  - `Hotfix Deploy`
- Confirm release metadata captured in deployment history.
- Confirm lock is removed after script completion.

## Post-Deploy

- Run `Post Deploy Validation` task.
- Verify endpoints:
  - `/`
  - `/account`
  - `/admin/login.php`
  - `/api/health/db`
- Verify auth/session flow remains healthy.
- Record release notes with release id, commit, and log path.

## If Any Step Fails

- Stop deployment activity immediately.
- Preserve evidence logs in `deploy/logs/`.
- Follow `docs/rollback-guide.md`.
