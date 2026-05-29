# Phase 1 Manual UI Sanity Gate

## Purpose
Final pre-production confidence pass using real admin and customer UI screens (not scripted).

## Preconditions
- Latest technical artifacts are green:
  - storage/recovery/phase1_uat/phase1_uat_results.json
  - storage/recovery/financial_integrity_report.json
- Tester uses production-like browser workflow.
- Evidence captured with screenshots per test.

## Test 1 - Online Order
Steps:
1. Create or open online order.
2. Upload payment proof.
3. Confirm payment.
4. Move order to preparing.
5. Move order to delivered.

Expected:
- Status transitions are allowed and saved.
- Payment state reflects confirmation.
- No accounting errors in UI.

Evidence:
- Screenshot each state transition page.

## Test 2 - BYOC Revision (Upward)
Steps:
1. Create BYOC order.
2. Submit upward revision.
3. Confirm revision.
4. Validate outstanding amount.

Expected:
- Revised total applied.
- Outstanding delta is correct.
- No duplicate or missing ledger impact signals in UI.

Evidence:
- Revision summary screenshot.
- Order financial block screenshot.

## Test 3 - Credit Collection
Steps:
1. Create credit order.
2. Receive partial payment.
3. Confirm outstanding decreases.
4. Receive final payment.
5. Confirm outstanding is zero.

Expected:
- Partial and final collections both post correctly.
- Collection status updates correctly.

Evidence:
- Before/after outstanding screenshots.
- Collection confirmation screenshots.

## Test 4 - Refund
Steps:
1. Select eligible paid order.
2. Issue refund.
3. Verify refund appears in reports pages.

Expected:
- Refund request and processing complete.
- Refund impact is visible in reporting UI.

Evidence:
- Refund confirmation screenshot.
- Relevant report page screenshot.

## Test 5 - Accounting Lock
Steps:
1. Close accounting period.
2. Attempt refund on locked order.

Expected:
- Operation is blocked.
- Error path includes ACCOUNTING_PERIOD_LOCKED.

Evidence:
- Lock settings screen screenshot.
- Blocked refund error screenshot.

## Signoff Record
- Tester name:
- Date:
- Environment:
- Result per test: PASS or FAIL
- Blocking issues (if any):

## Final Gate Decision
- If all 5 tests PASS: Phase 1 manual gate approved.
- If any test FAILS: remain in Phase 1 remediation and do not deploy to production.
