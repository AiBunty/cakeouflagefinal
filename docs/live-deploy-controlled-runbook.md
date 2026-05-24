# Live Controlled Deployment Runbook (StackCP)

This runbook is for zero-surprise overwrite deployment of Cakeouflage to live hosting.

## Preconditions

1. Local code is validated and lint clean.
2. Release artifacts exist in `backups/releases/<timestamp>/`.
3. `scripts/deploy/validate-production-env.ps1` passes on `.env.production`.
4. Operator has working FTP/SFTP and DB access from their machine.

## Phase A: Preflight (Must Pass)

1. Run env validation:

```powershell
Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"
pwsh ./scripts/deploy/validate-production-env.ps1 -EnvFile .env.production
```

2. Verify no accidental local env upload in package:

- Ensure `.env` is excluded.
- Ensure `.env.production` is handled separately.

3. Confirm release files:

- `cakeouflage_release_<timestamp>.zip`
- `cakeouflage_local_<timestamp>.sql`

## Phase B: Production Backups (Remote)

1. File backup:

- Download entire current live root to `backups/live/<timestamp>/files/`.
- Confirm archive can be opened and contains `index.php`, `app/`, `admin/`.

2. Database backup:

- Export full production DB to `backups/live/<timestamp>/db/live_before_overwrite.sql`.
- Verify dump file is non-empty.

Do not proceed without both backups.

## Phase C: File Overwrite Deploy

1. Connect to existing live root (no new folder structure).
2. Preserve user-generated paths:

- `uploads/`
- `media/` (if present)
- `storage/` runtime writable subfolders and contents required for continuity

3. Overwrite application code only:

- `index.php`
- `app/`
- `admin/`
- `client/`
- `public/` (except preserved uploads/media)
- `vendor/` if release includes dependency updates

4. Upload `.env.production` and rename to `.env` on server.

Never upload local `.env`.

## Phase D: Production DB Replace

1. Open production DB session.
2. Emergency backup (second safety dump).
3. Run:

```sql
SET FOREIGN_KEY_CHECKS = 0;
```

4. Drop all existing tables in target schema.
5. Import validated local SQL dump (`cakeouflage_local_<timestamp>.sql`).
6. Run:

```sql
SET FOREIGN_KEY_CHECKS = 1;
```

7. Confirm core tables exist:

- `admins`, `users`, `products`, `categories`, `cake_toppers`, `orders`, `order_items`, `otp_codes`, `otp_tokens`, `crm_settings`, `communication_templates`.

## Phase E: Live Validation

1. Homepage and catalog load.
2. Cart and checkout flow.
3. Customer OTP send/verify.
4. Registration OTP send/verify.
5. Admin OTP login and dashboard.
6. Manual order create with topper + note.
7. Invoice render and order details render.
8. SMTP runtime send check.

## Phase F: Monitoring (30 min)

Watch for:

- PHP fatals
- SMTP failures
- OTP failures
- DB connectivity issues
- Manual order save failures
- Invoice render errors

If any critical failure appears, execute rollback using the backups from Phase B.
