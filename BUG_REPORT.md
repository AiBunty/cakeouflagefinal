# Bug Report

## Critical

1. Refund API runtime crash: undefined method `requirePermission()`
- Endpoint: `POST /api/admin/orders/:id/refund/request`
- Observed status: `500`
- Evidence:
  - `storage/logs/api-response.log`
  - `storage/logs/php-error.log` (AdminApiController.php:7092)
- Impact:
  - Refund request path non-functional.
  - Blocks order cancellation/refund governance and compliance flow.

2. Refund API runtime crash: undefined method `requireAdminAuth()`
- Endpoint: `POST /api/admin/orders/:id/refund/process`
- Observed status: `500`
- Evidence:
  - `storage/logs/api-response.log`
  - `storage/logs/php-error.log` (AdminApiController.php:6883)
- Impact:
  - Alternate refund submission path also non-functional.

## High

3. Checkout preview endpoint fails consistently
- Endpoint: `POST /api/checkout/preview`
- Observed status: `422` during repeated online runs
- Evidence:
  - `storage/logs/qa_online_result.json`
  - `storage/logs/api-response.log`
- Impact:
  - Pre-checkout validation UX broken; customers may continue with inconsistent totals/availability signals.

4. Missing invoice creation for completed paid orders
- Evidence:
  - DB query snapshot in `QA_TEST_REPORT.md` (orders `6`,`7`,`8` -> `invoices` count `0`)
- Impact:
  - Financial reconciliation, compliance docs, and accounting export flows are incomplete.

## Medium

5. Missing communication events (email/CRM) for tested order lifecycle
- Evidence:
  - DB queries show `communication_logs` count `0` for tested orders.
  - `crm_push_logs` last-day count `0`.
- Impact:
  - Customer/admin notifications and CRM pipeline do not reflect lifecycle completion.

6. Slot reservation artifacts absent for tested fulfilled orders
- Evidence:
  - `slot_reservations` count `0` for orders `6`,`7`,`8`.
- Impact:
  - Capacity controls and slot auditability are not enforced for tested flows.

## Low

7. Admin refunds list endpoint permission mismatch in smoke run
- Endpoint: `GET /api/admin/refunds`
- Observed status: `403`
- Evidence: `storage/logs/qa_admin_endpoints.json`
- Impact:
  - Visibility for refund operations blocked for current admin principal/permission seed.
