# UAT Fix - Receivable and Report Reconciliation

## Scope
- UAT had reconciliation instability across receivable and scoped report checks.
- Constraint: Phase 1 only. No schema changes.

## Root Cause
- Reconciliation queries mixed lifecycle states and could compare report totals with ledger totals from a different recognition scope.
- Integrity checker initially used report sales definition broader than recognized-sales ledger scope.

## Code Fix
- Updated UAT reconciliation block to compare like-for-like recognized values.
- Updated integrity checker to latest UAT dataset scope and aligned sales report side to recognized payment states (`paid`, `credit`, `partially_refunded`, `refunded`).

## Files Updated
- storage/recovery/phase1_business_uat.php
- storage/recovery/financial_integrity_check.php
- storage/recovery/phase1_uat/steps/step12_report_reconciliation.html

## Verification
- UAT Step 12 all PASS:
  - Sales report vs ledger: 5550 vs 5550.
  - Collections report vs payment transactions: 5150 vs 5150.
  - Outstanding report vs receivable ledger: 400 vs 400.
  - Refund report vs refund ledger: 200 vs 200.
- Integrity report: overall_pass=true.
- Evidence:
  - storage/recovery/phase1_uat/steps/step12_report_reconciliation.html
  - storage/recovery/phase1_uat/screenshots/step12_report_reconciliation.png
  - storage/recovery/financial_integrity_report.json

## Result
- PASS in latest rerun.
