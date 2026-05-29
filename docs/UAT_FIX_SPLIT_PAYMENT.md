# UAT Fix - Split Payment

## Scope
- UAT failure: Step 05 split payment posted as full cash instead of 500 cash plus 500 bank.
- Constraint: Phase 1 only. No schema changes.

## Root Cause
- Receipt posting used a single payment account fallback path for mixed/split settlement events.
- Report aggregation could fall back to order payment method instead of verified split legs.

## Code Fix
- Updated split settlement routing to detect open receivable and post settlement against receivable when applicable.
- Updated receipt account resolution so bank-like methods (upi, bank_transfer, pos_card, payment_link) resolve to bank account mapping.
- Updated report aggregation to prefer verified `payment_transactions` split legs for cash and bank net amounts.

## Files Updated
- app/Services/PaymentSplitService.php
- app/Services/FinancialTransactionEngine.php
- app/Services/FinanceReportService.php

## Verification
- UAT Step 05 actual: split=ok, cash=500, bank=500, sales=1000.
- Ledger verification: cash_ledger_debit=500, bank_ledger_debit=500, sales_revenue_credit=1000.
- Evidence:
  - storage/recovery/phase1_uat/steps/step05_split_payment.html
  - storage/recovery/phase1_uat/screenshots/step05_split_payment.png
  - storage/recovery/phase1_uat/phase1_uat_results.json

## SQL Spot Checks
```sql
SELECT SUM(CASE WHEN la.code='CASH' THEN gle.debit_amount-gle.credit_amount ELSE 0 END) AS cash_net,
       SUM(CASE WHEN la.code='BANK' THEN gle.debit_amount-gle.credit_amount ELSE 0 END) AS bank_net
FROM general_ledger_entries gle
JOIN ledger_accounts la ON la.id = gle.account_id
WHERE gle.reference_type='order' AND gle.reference_id = :order_id;
```

## Result
- PASS in latest UAT run.
