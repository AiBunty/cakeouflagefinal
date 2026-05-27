# Final QA Summary

Generated at: 2026-05-25 16:20:53 +05:30

## Regression Runs

### 1) Online Flow (`scripts/qa/run_online_flow.ps1` via pwsh)
- `order_id`: 16
- `order_number`: CKF-20260525-444127
- `preview_status`: 200
- `place_status`: 201
- `confirm_payment_status`: 200
- `preparing_status`: 200
- `delivered_status`: 200
- Result: PASS

### 2) Manual Flow (`scripts/qa/run_manual_flow.ps1` via pwsh)
- `order_id`: 17
- `order_number`: MAN-20260525161718
- `confirm_payment_status`: 200
- `preparing_status`: 200
- `delivered_status`: 200
- Result: PASS

### 3) BYOC Flow (`scripts/qa/run_byoc_flow.ps1` via pwsh)
- `order_id`: 18
- `order_number`: BYOC-20260525-686010
- `quote_accept_status`: 200
- `confirm_payment_status`: 200
- `preparing_status`: 200
- `delivered_status`: 200
- Result: PASS

### 4) Refund Flow (`scripts/qa/run_refund_flow.ps1 -OrderId 17` via pwsh)
- `refund_request_status`: 200
- `refund_approve_status`: 422
- Approval response: `Dual-approval enforced: requester cannot approve/process the same refund.`
- Result: PASS (policy enforcement expected)

### 5) Admin Endpoint Smoke (`scripts/qa/run_admin_endpoint_smoke.ps1`)
- `/api/admin/dashboard/summary` -> 200
- `/api/admin/finance/summary` -> 200
- `/api/admin/reports/summary` -> 200
- `/api/admin/orders?limit=5` -> 200
- `/api/admin/refunds` -> 200
- Result: PASS

## QA Conclusion

- Core lifecycle and finance admin APIs are stable for release candidate.
- No new runtime regression was introduced by reconciliation/report hardening.