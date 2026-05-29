# UAT Fix - Revision Upgrade Outstanding

## Scope
- UAT failure: Step 06 revision upgrade posted adjustment but showed zero outstanding instead of 400.
- Constraint: Phase 1 only. No schema changes.

## Root Cause
- Snapshot computation used status-derived recognized collections too aggressively after revision.
- Revised total delta was not consistently reflected in effective total used by outstanding calculation.

## Code Fix
- Updated snapshot math to use `effective_total = COALESCE(revised_grand_total, grand_total)`.
- Restricted status-based recognition fallback so measured collections from transactions remain source of truth.

## Files Updated
- app/Services/OrderFinanceSnapshotService.php

## Verification
- UAT Step 06 actual: submit=PASS, confirm=PASS, outstanding=400.00, adj=400.
- Ledger verification: sales_adjustment_revenue_credit=400, outstanding_after_upgrade=400.
- Evidence:
  - storage/recovery/phase1_uat/steps/step06_revision_upgrade.html
  - storage/recovery/phase1_uat/screenshots/step06_revision_upgrade.png
  - storage/recovery/phase1_uat/phase1_uat_results.json

## SQL Spot Checks
```sql
SELECT order_number, grand_total, revised_grand_total, net_collected_amount, balance_due_amount
FROM orders
WHERE id = :order_id;
```

## Result
- PASS in latest UAT run.
