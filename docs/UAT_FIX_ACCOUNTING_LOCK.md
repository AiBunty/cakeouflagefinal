# UAT Fix - Accounting Lock Error Priority

## Scope
- UAT failure: lock test refund branch returned duplicate-refund error before accounting lock code.
- Constraint: Phase 1 only. No schema changes.

## Root Cause
- Refund workflow validated duplicate conditions before period lock, so locked-period behavior was not deterministic.

## Code Fix
- Reordered refund guard checks so accounting period lock is evaluated first.
- Returned expected lock code path immediately for locked orders.

## Files Updated
- app/Services/RefundService.php

## Verification
- UAT Step 10 actual:
  - refund=Refund request blocked by anti-fraud check: ACCOUNTING_PERIOD_LOCKED
  - revision=Order is locked for accounting period and cannot be revised
  - split=Order is locked for accounting period and cannot accept split payment
  - collection=Order is locked for accounting period and cannot accept split payment
- Evidence:
  - storage/recovery/phase1_uat/steps/step10_accounting_lock.html
  - storage/recovery/phase1_uat/screenshots/step10_accounting_lock.png
  - storage/recovery/phase1_uat/phase1_uat_results.json

## Result
- PASS in latest UAT run.
