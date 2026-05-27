# MariaDB 10.6 Targeted Regression Report (Pre-Production Gate)

Date: 2026-05-26
Scope: orders, refunds, CRM triggers, invoice, product image/defaults, admin settings, queue jobs
Environment: local Docker on MariaDB 10.6.25

## Executive Gate
- Overall decision: **FAIL (Hold production migration)**
- Reason: refund processing has a MariaDB/schema compatibility failure; CRM setting bootstrap query also has MariaDB SQL syntax incompatibility in runtime logs.

## Module Results

### 1) Orders
- Status: **PASS**
- Evidence:
  - Online flow succeeded end-to-end (place, confirm payment, preparing, delivered): [storage/logs/qa_online_result.json](storage/logs/qa_online_result.json)
  - Manual flow succeeded end-to-end: [storage/logs/qa_manual_result.json](storage/logs/qa_manual_result.json)
  - BYOC flow succeeded end-to-end: [storage/logs/qa_byoc_result.json](storage/logs/qa_byoc_result.json)
  - Final order states persisted as completed/paid for test orders 25/26/27 (DB verification during run).

### 2) Refunds
- Status: **FAIL (Blocker)**
- Evidence:
  - Refund request works and dual-approval enforcement works (requester cannot self-approve): [storage/logs/qa_refund_result.json](storage/logs/qa_refund_result.json)
  - Approval attempt by second admin returned 422 with DB error path in runtime.
  - Runtime error shows missing column used by refund processing:
    - [storage/logs/php-error.log](storage/logs/php-error.log)
    - Error text observed: "Unknown column 'settlement_reference' in 'field list'" from RefundService::processRefund.
  - DB state confirms refund stuck at approved, not processed (processed_at NULL for refund id 11).

### 3) CRM Triggers
- Status: **FAIL (Blocker for clean migration confidence)**
- Evidence:
  - CRM push log table exists and has successful rows, but runtime contains MariaDB SQL syntax errors in CRM setting bootstrap logic.
  - [storage/logs/php-error.log](storage/logs/php-error.log)
  - Error text observed: "ensureCrmSettingExists skipped ... near 'AS new ON DUPLICATE KEY UPDATE ...'".
- Interpretation:
  - Existing rows/settings allow some CRM operations to pass, but SQL is not MariaDB-safe and can fail in new/clean states.

### 4) Invoice
- Status: **PASS**
- Evidence:
  - Invoices are generated and marked paid for test orders (order_id 25/26/27).
  - DB checks returned invoice_status=paid with paid_amount == grand_total for latest test invoices.

### 5) Product Image / Defaults
- Status: **PASS (with API contract warning)**
- Evidence:
  - Admin default image setting exists in settings: default_product_image_url present.
  - Products with empty featured_image: 0 (DB check).
  - Catalog API returns image fields (image, hover_image, featured_image) and product records renderable.
- Warning:
  - Catalog payload items did not include image_url key in sampled response; if frontend/contracts depend on image_url specifically, add compatibility mapping.

### 6) Admin Settings
- Status: **PASS**
- Evidence:
  - Core admin API smoke endpoints returned 200:
    - /api/admin/dashboard/summary
    - /api/admin/finance/summary
    - /api/admin/reports/summary
    - /api/admin/orders
    - /api/admin/refunds
  - JSON result: [storage/logs/qa_admin_endpoints.json](storage/logs/qa_admin_endpoints.json)

### 7) Queue Jobs
- Status: **PASS (degraded)**
- Evidence:
  - Cron queue endpoint executes successfully (HTTP 200): /cron/queue/process
  - Queue movement observed in one run:
    - queued: 86 -> 68
    - completed: 99 -> 112
    - failed: 18 -> 23
- Interpretation:
  - Queue worker is functioning, but some jobs still fail; should be triaged before production hard freeze.

## Critical Blockers Before Production Migration
1. Refund processing schema/code mismatch
- Fix RefundService SQL to align with actual refund_transactions schema (or add required columns via migration if intentional).
- Re-run dual-approval flow until a refund reaches status=processed with processed_at set.

2. CRM setting bootstrap SQL incompatibility on MariaDB
- Rewrite ensureCrmSettingExists SQL to MariaDB-compatible upsert syntax.
- Re-test clean-state CRM trigger path.

## Recommended Next Validation Cycle (after fixes)
- Re-run scripts:
  - scripts/qa/run_online_flow.ps1
  - scripts/qa/run_manual_flow.ps1
  - scripts/qa/run_refund_flow.ps1
  - scripts/qa/run_byoc_flow.ps1
  - scripts/qa/run_admin_endpoint_smoke.ps1
- Verify zero new errors in: [storage/logs/php-error.log](storage/logs/php-error.log)
- Re-run queue cron and confirm failed queue_jobs does not increase for tested scenarios.

## Migration Gate Verdict
- Do not proceed with next production migration step until Refunds and CRM Triggers blockers above are fixed and re-validated.
