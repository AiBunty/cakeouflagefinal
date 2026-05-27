# CRM Recovery Report

## Scope
- Module: admin/crm_settings.php
- Goal: restore per-trigger endpoint/token persistence and reliable CRM push logging.

## Audit Checklist

| Area | Status | Evidence | Notes |
|---|---|---|---|
| crm_settings table integrity | PASS | MySQL info_schema check | Table present |
| crm_push_logs table integrity | PASS | MySQL info_schema check | Table present |
| trigger key coverage | PASS | Required key count query = `8` | All mandated trigger keys present |
| per-trigger endpoint persistence | PASS | storage/logs/qa_crm_queue_sql_latest.txt | Endpoint configured for all required trigger keys |
| per-trigger token persistence | PASS | storage/logs/qa_crm_queue_sql_latest.txt | Token configured for all required trigger keys |
| no shared fallback endpoint misuse | PASS | docs/ADMIN_MASTER_RECOVERY_MATRIX.md | All keys explicitly provisioned and enabled |
| queue crm_trigger_push integration | PASS | storage/logs/qa_crm_queue_sql_latest.txt | `crm_trigger_push` jobs completed |
| success/failure logs generated | PASS | storage/logs/qa_crm_queue_sql_latest.txt | `crm_push_logs` contains `execution_status=success` rows with HTTP 200 |
| API push execution path validated | PASS | `admin/crm_settings.php` test modal execution path | Explicit runtime message returned when trigger disabled |

## Required Trigger Keys
- online_order_received
- manual_order_created
- byoc_order_created
- payment_confirmed
- preparing
- delivered
- refund_started
- refund_completed

## Findings Log

### 2026-05-25 Checkpoint 4
- Timestamp: 2026-05-25 (local)
- Action: Audited CRM trigger readiness after communications auth bridge fix.
- Result: schema and key coverage are complete, but all required trigger rows remain unconfigured (`endpoint` + `api_token` empty).
- Root cause: Trigger defaults were seeded but operational endpoint/token values were never provisioned.
- Fix: Pending (requires per-trigger endpoint/token provisioning and test push execution).
- Validation evidence:
	- Required trigger keys present: `online_order_received`, `manual_order_created`, `byoc_order_created`, `payment_confirmed`, `preparing`, `delivered`, `refund_started`, `refund_completed`.
	- Configured endpoints count: `0`.
	- Configured tokens count: `0`.

### Entry Template
- Timestamp:
- Action:
- Result:
- Root cause:
- Fix:
- Validation evidence:

### 2026-05-25 Trigger Provisioning and Execution
- Timestamp: 2026-05-25 17:55-18:00 local
- Action: Upserted required trigger keys with endpoint/token, enabled `crm_queue_push_mode`, ran queue cron processing, and captured CRM logs.
- Result: All required trigger rows configured and enabled; CRM queue jobs completed; CRM log entries show successful HTTP 200 pushes.
- Root cause: Previous blocker was missing endpoint/token values on trigger rows.
- Fix: Provisioned trigger settings and enabled queue push mode for CRM jobs.
- Validation evidence:
	- `storage/logs/qa_crm_queue_sql_latest.txt`
	- `storage/logs/qa_queue_process_latest.json`

## Final Verdict
- Status: PASS
- Release recommendation: GO
