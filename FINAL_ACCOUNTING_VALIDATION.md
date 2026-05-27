# Final Accounting Validation

Generated at: 2026-05-25 16:20:53 +05:30

## Data Source

- `GET /api/admin/finance/summary` (authenticated admin session)
- `GET /api/admin/reports/summary` (authenticated admin session)
- QA artifacts in `storage/logs/qa_*.json`

## Finance Summary Snapshot (Today)

- `total_invoices`: 9
- `paid_invoices`: 9
- `unpaid_invoices`: 0
- `overdue_invoices`: 0
- `part_paid_invoices`: 0
- `retail_receivables`: 0
- `b2b_receivables`: 0
- `total_receivables`: 0
- `cash_collections`: 0
- `bank_collections`: 13836.1
- `net_collections`: 13836.1
- `refunded_total`: 0
- `ledger_cash_collections`: 0
- `ledger_bank_collections`: 13836.1
- `ledger_net_revenue`: 13836.1
- `reconciliation_status`: ok
- `reconciliation_variance`: 0
- `reconciliation_window`: 2026-05-25 to 2026-05-25

## Reports Summary Snapshot (Month-to-date)

- `retail_orders`: 18
- `b2b_orders`: 0
- `pending_invoices`: 0
- `this_month_collected`: 13836.1
- `this_month_refunded`: 0
- `this_month_outstanding`: 5805.6
- `pending_refunds`: 5
- `processed_refunds`: 0
- `reconciliation_status`: ok
- `reconciliation_variance`: 0
- `reconciliation_window`: 2026-05-01 to 2026-05-25

## Reconciliation Outcome

- Order-side realized collections and ledger-side recognized collections are aligned for validated windows.
- Refund reporting remains policy-driven and approval-controlled.
- No accounting mismatch detected in the current release-candidate dataset (`variance = 0`).

## Policy Notes

- Refund dual-approval remains enforced. A requester cannot self-approve/process the same refund transaction.