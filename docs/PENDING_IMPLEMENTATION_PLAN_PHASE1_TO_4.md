# Pending Implementation Plan (Phases 1 to 4)

## Status Summary
- Phase 0 baseline evidence: completed.
- Phase 0 UAT matrix (30+): completed in docs/UAT_MATRIX_PHASE0_30PLUS.md.
- Phase 1 targeted accounting remediation: completed and revalidated.
- Phase 1 technical signoff: approved (see docs/PHASE1_UAT_SIGNOFF.md).
- Financial integrity checker: overall_pass=true.
- Production gate: still requires final manual UI sanity pass before any production deployment.

## Manual Gate Before Deployment (Required)
Run these five UI tests manually (not scripted) and record results:
1. Online order flow:
- upload proof
- confirm payment
- preparing
- delivered
2. BYOC revision flow:
- create order
- revise upward
- verify outstanding
3. Credit collection flow:
- create credit order
- receive partial payment
- receive final payment
4. Refund flow:
- issue refund
- verify reports
5. Accounting lock flow:
- close period
- attempt refund
- expected: ACCOUNTING_PERIOD_LOCKED

## Phase 2 Start Criteria
Phase 2 may begin after:
1. Phase 1 technical signoff remains green.
2. Manual UI sanity pass above is completed and accepted.

## Phase 2 Execution Order (Exact)

### Module 1 - Vendor Master
Build:
- Vendor
- Vendor Ledger
- Vendor Outstanding

### Module 2 - Expense Module
Build:
- Expense Categories
- Expense Entry
- Expense Posting

Rule:
- GL-integrated from day one.

### Module 3 - Inventory Foundation
Build:
- Inventory Items
- Units
- Stock Ledger

Rule:
- Do not start recipe consumption yet.

### Module 4 - Purchase Entry
Build:
- Purchase Voucher
- Vendor Bill
- Stock Receipt

### Module 5 - Payables
Build:
- Vendor Outstanding
- Vendor Payment
- Vendor Ledger

## Explicit Deferrals (Do Not Start Yet)
- BOM
- Recipe costing
- Production consumption
- Automatic ingredient deduction
- Advanced profitability
- Forecasting

## Phase 3 (Reporting + Close Controls)
1. Expand reporting for Vendor, Expense, Inventory, Procurement tie-outs.
2. Add close-control checks for payable and stock reconciliation.
3. Validate cross-report consistency against ledger.

## Phase 4 (Validation + Rollout)
1. Execute full UAT matrix for Phase 1 and 2 features.
2. Complete cross-report tie-out signoff.
3. Run staged rollout plan with rollback playbook.

## Immediate Next Action
- Execute manual UI sanity pass and append outcome to docs/PHASE1_UAT_SIGNOFF.md.
- Only then begin Module 1 implementation.
