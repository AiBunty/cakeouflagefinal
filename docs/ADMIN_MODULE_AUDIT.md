# Admin Module Audit

## Phase A: Baseline Checkpoint (Completed)

- Timestamp: 2026-05-25 16:57:53 local
- Runtime: Docker Compose (`cakeouflage-web`, `cakeouflage-db`) healthy
- PHP CLI: 8.1.34
- MySQL: 8.0.45
- Active SQL mode:
  - `ONLY_FULL_GROUP_BY`
  - `STRICT_TRANS_TABLES`
  - `NO_ZERO_IN_DATE`
  - `NO_ZERO_DATE`
  - `ERROR_FOR_DIVISION_BY_ZERO`
  - `NO_ENGINE_SUBSTITUTION`

### Safety Backup

- Baseline SQL backup created:
  - `storage/backups/admin_recovery_baseline_20260525_165753.sql`
  - Size: 1,071,614 bytes

## Phase B: Module Mapping Snapshot (Initial)

This snapshot verifies file presence and records recovery status. Functional pass/fail execution is tracked in later checkpoints.

| Area | Primary Entry | Status | Notes |
|---|---|---|---|
| Admin shell/layout | `admin/layout.php` | PASS | Previously had header/exception interaction; bootstrap guard already patched. |
| Auth gate | `admin/includes/auth.php` | PASS | Legacy admin auth in place. |
| Login flow | `admin/login.php` + `admin/login_process.php` | PASS | Files present for credential/session entry. |
| Orders board | `admin/orders.php` | PASS | Delivered->completed flow already aligned in prior fix cycle. |
| Status updates sync | `admin/update_order_status.php` | PASS | Canonical status handling in place. |
| Status updates async | `admin/api/update-order-status-async.php` | PASS | Canonical status handling in place. |
| BYOC module | `admin/build-your-own-cake.php` | PASS | Strict-mode query patch applied in this cycle. |
| Communications | `admin/communications.php` | PASS | Template seeding logic present. |
| CRM settings | `admin/crm_settings.php` + `admin/update_crm_settings.php` | PASS | Settings UI and write path present. |
| Product media | `app/Services/ProductImageService.php` | PASS | Placeholder remap logic already applied in prior cycle. |

## Phase C: Critical Root-Cause Fixes Applied (Checkpoint 1)

### 1) Strict-mode BYOC fallback query

- File: `admin/build-your-own-cake.php`
- Root cause:
  - Query used explicit zero-date literal comparison on `deleted_at`, which is brittle under strict SQL mode and legacy data combinations.
- Fix:
  - Replaced direct literal predicate with strict-safe `NULLIF(deleted_at, '0000-00-00 00:00:00') IS NULL` pattern.

### 2) SQL mode consistency per connection session

- Files:
  - `admin/includes/db.php`
  - `app/Core/Database.php`
- Root cause:
  - Mixed connection stacks (MySQLi + PDO) can run with different session SQL modes across request paths.
- Fix:
  - Enforce a uniform strict session `sql_mode` immediately after connection initialization in both stacks.

## Validation Evidence

- Syntax check:
  - `php -l admin/build-your-own-cake.php` -> no syntax errors
  - `php -l admin/includes/db.php` -> no syntax errors
  - `php -l app/Core/Database.php` -> no syntax errors
- Baseline communication/CRM DB counts:
  - `communication_templates`: 26
  - `communication_logs`: 70
- Relevant table presence (partial):
  - `crm_settings`, `crm_push_logs`
  - `whatsapp_settings`, `whatsapp_templates`, `whatsapp_template_mappings`, `whatsapp_template_versions`, `whatsapp_template_variables`
  - `whatsapp_template_approval_logs`, `whatsapp_template_buttons`, `whatsapp_template_sync_logs`

## Phase D: Module Route Health + CRM Defaults Recovery (Checkpoint 2)

### Route health probes (unauthenticated baseline)

- `/admin/login.php` -> HTTP 200
- `/admin/orders.php` -> HTTP 302 (auth redirect expected)
- `/admin/build-your-own-cake.php` -> HTTP 302 (auth redirect expected)
- `/admin/communications.php` -> HTTP 302 (auth redirect expected)
- `/admin/crm_settings.php` -> HTTP 302 (auth redirect expected)
- `/admin/update_order_status.php` -> HTTP 302 (auth redirect expected)

Result: No HTTP 500 responses detected on critical admin routes.

### CRM defaults persistence hardening

- File patched: `admin/includes/crm_settings_helpers.php`
- Change:
  - `ensure_crm_support_settings()` now also upserts missing follow-up defaults into `settings`.
  - Existing non-empty settings are preserved and never overwritten.
- Runtime verification:
  - Invoked bootstrap in container runtime (`crm_bootstrap_ok`).
  - Verified persisted keys in `settings`:
    - `google_review_link`
    - `review_delay_days`
    - `quarterly_follow_up_interval_months`
    - `annual_reminder_days_before`
    - `annual_reminder_basis`
    - `celebration_reminder_days_before`
    - `celebration_combined_email_on_same_day`
    - `whatsapp_send_mode`
    - `crm_queue_push_mode`
    - `required_fields_note`

### CRM required trigger keys (table integrity)

- Required key count check passed: `8/8`
- Keys present:
  - `online_order_received`
  - `manual_order_received`
  - `payment_confirmed`
  - `reject_order`
  - `ready_order`
  - `order_delivered`
  - `follow_up_review`
  - `annual_reorder`

## Next Execution Steps

1. Execute authenticated browser/API proof pass for BYOC, communications, and CRM admin pages (including form submits).
2. Run queue worker smoke test for communication and CRM trigger jobs using seeded defaults.
3. Capture `communication_logs`, `crm_push_logs`, and PHP error log excerpts as evidence.
4. Continue module-by-module recovery with explicit pass/fail entries in this file.

## Phase E: Authenticated Browser/API + Queue Smoke Proof (Checkpoint 3)

### Browser proof pass (authenticated)

Environment: Active authenticated admin session (`Dcore`) via shared browser page.

1. BYOC page submit path
   - Opened `admin/build-your-own-cake.php` successfully.
   - Executed filter submit (`status=closed` + Apply).
   - Result URL after submit: `http://localhost:8080/admin/build-your-own-cake.php?q=&status=closed`.
   - Result state: table rendered with `No Build Your Own Cake leads found.` for closed filter.

2. Communications submit path
   - Opened `admin/communications.php` successfully.
   - Executed `Save Admin Routing` action.
   - DB persistence verified in `settings`:
     - `communication_admin_to_email = cakeouflage@gmail.com`
     - `communication_admin_cc_admin_ids = ""`
     - `updated_at = 2026-05-25 17:07:38`

3. CRM submit path
   - Opened `admin/crm_settings.php` successfully.
   - Opened modal via first `Test Push` action and submitted test payload:
     - Name: `QA Browser Probe`
     - Email: `qa.crm.browser@example.com`
   - Runtime result message: `CRM trigger is disabled. Enable it in CRM Settings before testing.`
   - Interpretation: submit path works and returns explicit non-silent validation status.

### API proof pass (runtime probes)

Artifact: `storage/logs/qa_phaseE_proof_20260525_171200.txt`

- `401 /api/admin/queue/jobs` (auth-protected endpoint reachable).
- `404 /api/admin/communications/templates`
- `404 /api/admin/whatsapp/mappings/build_your_cake_quote_whatsapp`
- `404 /api/admin/queue/process-now`

Observation: Queue admin API endpoint exists and is auth-gated; the other probed paths are not current route names in this runtime.

### Queue worker smoke run (communication + CRM job class focus)

Primary compact artifact: `storage/logs/qa_queue_smoke_compact_20260525_171110.txt`

- Worker execution result:
  - `processed=10`
  - `completed=6`
  - `failed=0`
  - `requeued=4`
- Requeued job errors were non-communication media jobs:
  - `No WebP encoder available (imagewebp/cwebp missing)`
- Communication queue delta from artifact:
  - Before: `send_communication queued:46, processing:1, completed:23`
  - After: `send_communication queued:34, processing:1, completed:35`

### Post-smoke DB evidence snapshot

- `queue_jobs` grouped state:
  - `send_communication queued=28`
  - `send_communication completed=42`
  - `media_image_optimize queued=17`
- `communication_logs` latest rows (IDs 102..91) remain `queued` with valid event keys/recipients.
- `crm_push_logs` current total rows: `0` (no CRM endpoint/token enabled, therefore no live CRM push rows created).

### Error-log evidence captured

From `storage/logs/php-error.log` tail in `storage/logs/qa_phaseE_proof_20260525_171200.txt`:

1. BYOC strict datetime failure still occurs in another POST path:
   - `Incorrect DATETIME value: '0000-00-00 00:00:00'`
   - source: `/admin/build-your-own-cake.php` line 195.

2. Dashboard finance query/schema mismatch (already visible in browser):
   - Unknown column `o.followup_status`
   - Unknown column `advance_received_amount`

These are now promoted as explicit blockers for next checkpoint (no silent failures).

## Updated Next Execution Steps

1. Patch BYOC line-195 strict-mode branch causing zero-datetime POST failure; rerun BYOC submit proof.
2. Stabilize dashboard finance query compatibility for missing columns (`o.followup_status`, `advance_received_amount`) with schema-aware fallback.
3. Execute a second queue smoke after the above fixes and verify movement from `communication_logs.status=queued` to sent/failed terminal states with provider responses.
4. Re-run authenticated API probes using exact registered admin API routes from router/controller map and attach 2xx/401 evidence only.

## Phase F: Auth Bridge Hardening + Communications Runtime Recovery (Checkpoint 4)

### Root cause closed

- Root cause: admin pages and API routes were reading different session storage due boot order drift.
  - `admin/includes/auth.php` started session before `app/bootstrap.php`.
  - `app/bootstrap.php` sets a custom session save path (`storage/sessions`) only when it starts the session.
  - Result: legacy admin page auth and `/api/admin/*` auth read different session backends.

### Fix applied

- `admin/includes/auth.php`
  - bootstraps `app/bootstrap.php` first.
  - only starts session if still not started.
- `admin/login.php`
  - removed early manual `session_start()` so login uses the same bootstrap-managed session backend.

### Verification evidence

- Syntax checks:
  - `php -l admin/includes/auth.php` -> no syntax errors
  - `php -l admin/login.php` -> no syntax errors
- Live authenticated API probes (browser runtime):
  - `GET /api/admin/communication/templates` -> `200`, `{"success":true,...}`
  - `GET /api/admin/whatsapp/mappings` -> `200`, `{"success":true,...}`
- Communications UI runtime:
  - `admin/communications.php` renders populated template list (no empty-state lockout).

### New blocking issue surfaced during authenticated run

- `admin/dashboard.php` currently throws:
  - `Unknown column 'o.followup_status'`
  - `Unknown column 'advance_received_amount'`
- This remains an explicit release blocker until schema alignment or query fallback is applied.
