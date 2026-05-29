# UAT Fix - Refund Posting Visibility

## Scope
- UAT failure: Step 08 refund updated order totals but refund ledger check did not show expected SALES_REFUNDS debit.
- Constraint: Phase 1 only. No schema changes.

## Root Cause
- Refund financial transaction reference scope used refund-transaction reference type, which broke order-scoped reconciliation and customer ledger expectations.

## Code Fix
- Updated refund posting to use order-scoped reference (`reference_type='order'`, `reference_id=orderId`).
- Preserved refund transaction traceability via metadata field `refund_transaction_id`.

## Files Updated
- app/Services/FinancialTransactionEngine.php

## Verification
- UAT Step 08 actual: submit=PASS, approve=PASS, refund_ledger=200.
- Ledger verification: sales_refunds_debit=200, order_total_refunded=200, customer_statement_events=3.
- Evidence:
  - storage/recovery/phase1_uat/steps/step08_refund.html
  - storage/recovery/phase1_uat/screenshots/step08_refund.png
  - storage/recovery/phase1_uat/phase1_uat_results.json

## SQL Spot Checks
```sql
SELECT ft.id, ft.reference_type, ft.reference_id, ft.metadata_json
FROM financial_transactions ft
WHERE ft.reference_type='order' AND ft.reference_id=:order_id
  AND ft.transaction_type='refund';
```

## Result
- PASS in latest UAT run.
