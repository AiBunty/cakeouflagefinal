# Fix Log

## A) Environment and Runtime Stabilization

1. Updated local runtime config for QA observability
- `.env`: enabled debug and non-secure local cookie behavior
- `docker-compose.yml`: aligned local session cookie security/debug behavior

2. Enabled broader runtime tracing for API/debug
- `index.php`: API response status logging to `storage/logs/api-response.log`
- `app/Services/MailService.php`: debug-focused SMTP trace enhancements
- `app/bootstrap.php`: explicit session save path binding to `storage/sessions`

## B) Database Stabilization Per Runtime Errors

1. Repaired missing auth support table
- Created `auth_rate_limits` when absent to unblock auth path.

2. Repaired order schema drift blocking place-order path
- Added missing `orders` columns observed from runtime errors:
  - `customer_phone_e164`
  - `delivery_street`
  - `delivery_maps_link`
  - `advance_amount`
  - `payment_confirmed_at`
  - `payment_confirmed_by_admin_id`
  - `outstanding_amount`
  - `amount_collected`

## C) QA Automation Artifacts Added

Created scripts under `scripts/qa/`:

1. `run_online_flow.ps1`
- Validates online flow from customer login to delivered status.

2. `run_manual_flow.ps1`
- Validates manual-mode lifecycle.
- Uses SQL seed fallback for manual order creation in local env where legacy UI auth loop blocks deterministic form path.

3. `run_byoc_flow.ps1`
- Seeds BYOC quote artifacts and validates quote-accept to delivered lifecycle.

4. `run_refund_flow.ps1`
- Attempts refund request+approve flow; currently records failure due backend controller exceptions.

5. `run_admin_endpoint_smoke.ps1`
- Authenticated smoke checks for dashboard/finance/reports/orders/refunds endpoints.

## D) Evidence Outputs Produced

- `storage/logs/qa_online_result.json`
- `storage/logs/qa_manual_result.json`
- `storage/logs/qa_byoc_result.json`
- `storage/logs/qa_refund_result.json`
- `storage/logs/qa_admin_endpoints.json`
- `storage/logs/api-response.log`
- `storage/logs/php-error.log`

## E) Remaining Known Gaps (Not Fixed in this run)

- Refund controllers reference undefined methods in `AdminApiController`.
- Invoice generation not observed for tested completed orders.
- CRM/email logs absent for tested orders.
- Slot reservation artifacts absent for tested orders.
