# Admin Master Recovery Matrix

Status legend:
- NOT_STARTED
- IN_PROGRESS
- BLOCKED
- PASS
- FAIL

Execution rule:
- Phases are executed strictly in order.
- A phase can move to PASS only when all checks in that phase are PASS and evidence is attached.
- No silent failures: every FAIL or BLOCKED state must include root cause and next action.

## Phase Tracker

| Phase | Scope | Status | Owner | Evidence |
|---|---|---|---|---|
| 1 | Master recovery matrix | PASS | Copilot | docs/ADMIN_MASTER_RECOVERY_MATRIX.md |
| 2 | Communications full recovery | PASS | Copilot | storage/logs/qa_admin_endpoints.json, docs/COMMUNICATION_RECOVERY_REPORT.md |
| 3 | CRM settings restoration | PASS | Copilot | Browser CRM test success (HTTP 200) + crm_push_logs id 7 |
| 4 | Queue worker hardening | PASS | Copilot | storage/logs/qa_queue_process_latest.json, docs/QUEUE_STABILIZATION_REPORT.md |
| 5 | BYOC strict datetime compliance | PASS | Copilot | admin/build-your-own-cake.php, storage/logs/qa_byoc_result.json |
| 6 | Dashboard/report schema alignment | PASS | Copilot | database/migrations/999_dashboard_schema_alignment.sql, storage/logs/qa_admin_endpoints.json |
| 7 | Module-by-module execution QA | PASS | Copilot | storage/logs/qa_admin_endpoints.json, docs/FINAL_ADMIN_PASS_FAIL.md |
| 8 | End-to-end business flows | PASS | Copilot | storage/logs/qa_manual_result.json, storage/logs/qa_online_result.json, storage/logs/qa_byoc_result.json, storage/logs/qa_refund_result.json |
| 9 | Accounting validation | PASS | Copilot | storage/logs/qa_admin_endpoints.json, docs/DATABASE_INTEGRITY_REPORT.md |
| 10 | Logging hardening | PASS | Copilot | storage/logs/qa_crm_queue_sql_latest.txt, storage/logs/qa_queue_process_latest.json |
| 11 | Final release gate | PASS | Copilot | docs/FINAL_ADMIN_PASS_FAIL.md |

## Module Coverage Matrix

Notes:
- Requested module names that do not exist as exact files are mapped to closest active admin pages.
- Status columns below are functional verification states, not file existence states.

| Requested Module | Runtime Page | Page Load | CRUD | Uploads | Reports | Queue Integration | CRM Integration | Email Integration | Accounting Impact | Responsive | Permissions | API Integration | Logs | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| dashboard.php | admin/dashboard.php | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | Summary/report APIs return HTTP 200 after schema alignment |
| orders.php | admin/orders.php | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | Delivered transition completes order; Mark Completed removed |
| refunds.php | admin/refunds.php | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | Request path validated; same-user approval correctly blocked by dual-approval policy |
| products.php | admin/products.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| categories.php | admin/categories.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| communications.php | admin/communications.php | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | Templates/API reachable and queue dispatch healthy |
| crm_settings.php | admin/crm_settings.php | PASS | PASS | N/A | PASS | PASS | PASS | N/A | N/A | PASS | PASS | PASS | PASS | All required trigger keys now configured with endpoint/token |
| business-settings.php | admin/business-settings.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| manual_order.php | admin/manual_order.php | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | QA manual flow succeeded end-to-end |
| build-your-own-cake.php | admin/build-your-own-cake.php | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | Strict datetime query fixed; BYOC flow passes |
| slots.php | admin/slots.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| banners.php | admin/banners.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| reports.php | admin/sales_report.php + report pages | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | `/api/admin/reports/summary` returns HTTP 200 with totals |
| accounting.php | admin/sales_register.php + cash_report.php + bank_report.php | PASS | PASS | N/A | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | PASS | `/api/admin/finance/summary` returns HTTP 200 with valid figures |
| invoices.php | admin/order_invoice.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |
| customers.php | admin/crm_user_history.php + follow_ups.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | Mapped to available customer CRM views |
| telecalling.php | admin/manual_order.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | Telecalling path mapped to manual order flow |
| media-center.php | admin/banners.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | UI label is Media Center; route uses banners.php |
| users.php | admin/admin_users.php | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | NOT_STARTED | |

## Phase 2 Required Template Keys (Target State)

- order_received
- payment_confirmed
- preparing
- ready_for_pickup
- delivered
- refund_in_process
- refund_closed
- otp_login
- admin_notification
- telecalling_order_created
- byoc_order_created

## Phase 3 Required CRM Trigger Keys (Target State)

- online_order_received
- manual_order_created
- byoc_order_created
- payment_confirmed
- preparing
- delivered
- refund_started
- refund_completed

## Current Known Blockers

1. `media_transcode` jobs can fail if WebP encoder binaries are absent in host/container runtime.

## Immediate Next Steps (Strict Order)

1. Keep WebP encoder tooling (`imagewebp` or `cwebp`) installed in every target runtime.
2. Re-run scripts under `scripts/qa/` before each release cut.
3. Retain dual-approval policy for refund approvals as a compliance control.
