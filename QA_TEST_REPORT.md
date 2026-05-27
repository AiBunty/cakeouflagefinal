# QA Test Report (Local Regression Audit)

Date: 2026-05-25
Environment: Docker local (`cakeouflage-web`, `cakeouflage-db`), app at `http://localhost:8080`
Scope: Order lifecycle (online/manual/BYOC), admin dashboards/reports, accounting entries, invoices, CRM/email traces, slot reservations, refunds.

## 1) Execution Summary

- Online flow: PARTIAL PASS
- Manual flow: PASS (with creation fallback)
- BYOC flow: PASS
- Refund lifecycle: FAIL (controller runtime errors)
- Accounting posting: PARTIAL PASS
- Dashboard/reporting APIs: PARTIAL PASS
- Invoicing: FAIL
- CRM/email triggers for tested orders: FAIL
- Slot reservations for tested orders: FAIL

## 2) Test Cases and Results

1. Online order end-to-end (`login -> cart -> coupon -> preview -> place -> confirm -> preparing -> delivered`)
- Result: PARTIAL PASS
- Evidence: `storage/logs/qa_online_result.json`
- Notes:
  - `preview_status=422` (consistent failure)
  - `place_status=201` and lifecycle transitions returned `200`

2. Manual order end-to-end
- Result: PASS (with controlled fallback)
- Evidence: `storage/logs/qa_manual_result.json`
- Notes:
  - Legacy manual UI auth path had redirect loop in local setup.
  - Manual order was SQL-seeded in manual mode, then lifecycle executed via live admin APIs:
    `confirm-payment`, `status=preparing`, `status=delivered` all `200`.

3. BYOC quote accept to delivered
- Result: PASS
- Evidence: `storage/logs/qa_byoc_result.json`
- Notes:
  - Quote accept required CSRF/session binding.
  - Accept endpoint returned `200` with order creation payload.
  - Payment confirm + status transitions returned `200`.

4. Admin dashboards/reporting API smoke
- Result: PARTIAL PASS
- Evidence: `storage/logs/qa_admin_endpoints.json`
- Notes:
  - `/api/admin/dashboard/summary` -> `200`
  - `/api/admin/finance/summary` -> `200`
  - `/api/admin/reports/summary` -> `200`
  - `/api/admin/orders?limit=5` -> `200`
  - `/api/admin/refunds` -> `403` (permission/configuration issue)

5. Refund lifecycle (`request -> approve`)
- Result: FAIL
- Evidence: `storage/logs/api-response.log`, `storage/logs/php-error.log`, `storage/logs/qa_refund_result.json`
- Notes:
  - `/api/admin/orders/:id/refund/request` -> `500`
  - `/api/admin/orders/:id/refund/process` -> `500`
  - Runtime exceptions indicate undefined controller methods.

## 3) Data Integrity / Accounting Validation

Validated using DB queries against orders `6`, `7`, `8`:

- Orders reached terminal state:
  - `order_status=completed`
  - `payment_status=paid`
- Financial transactions posted:
  - `financial_transactions` contains `payment_received` rows for all 3 orders.
- GL entries posted:
  - debit `BANK_CLEARING`, credit `SALES_REVENUE` for all 3 orders.

Observed gaps:
- `invoices` rows for tested orders: `0`
- `refund_transactions` rows for tested orders: `0`
- `communication_logs` rows for tested orders: `0`
- `crm_push_logs` in last day: `0`
- `slot_reservations` rows for tested orders: `0`

## 4) Key Evidence Files

- `storage/logs/qa_online_result.json`
- `storage/logs/qa_manual_result.json`
- `storage/logs/qa_byoc_result.json`
- `storage/logs/qa_admin_endpoints.json`
- `storage/logs/qa_refund_result.json`
- `storage/logs/api-response.log`
- `storage/logs/php-error.log`

## 5) Release Readiness Verdict

Current readiness: **NOT READY FOR PRODUCTION RELEASE**.
Primary blockers are refund runtime exceptions, missing invoice generation, and missing CRM/email/slot artifacts on tested lifecycle orders.
