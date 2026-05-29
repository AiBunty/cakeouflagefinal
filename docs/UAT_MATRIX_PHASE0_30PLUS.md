# UAT Matrix (Phase 0 Baseline + 30+ Scenarios)

## Scope
- Baseline and regression coverage for orders, payments, collections, refunds, revisions, and reporting.
- Focus on pre-implementation evidence before broader Phase 1+ changes.

## Legend
- Priority: P0 critical, P1 high, P2 medium
- Result: PASS / FAIL / BLOCKED

| ID | Scenario | Priority | Precondition | Expected Outcome | Result |
|---|---|---|---|---|---|
| UAT-001 | Create pending order | P0 | Customer/cart ready | Order saved with `pending_payment` | TODO |
| UAT-002 | Confirm payment via UPI | P0 | Pending order exists | Payment status becomes `paid`; audit log written | TODO |
| UAT-003 | Confirm payment via COD | P1 | Pending order exists | Payment method `cod`; status aligned | TODO |
| UAT-004 | Confirm payment via credit | P0 | Pending order exists | Payment status `credit`; balance collectible | TODO |
| UAT-005 | Move to preparing | P1 | Confirmed order | Order status transitions to `preparing` | TODO |
| UAT-006 | Move to out_for_delivery | P1 | Preparing order | Status updated and timeline event written | TODO |
| UAT-007 | Delivered action closes order | P0 | Confirmed/preparing order | Persisted status becomes `completed` | TODO |
| UAT-008 | Mark Completed button hidden | P0 | Orders list loaded | No explicit Mark Completed action shown | TODO |
| UAT-009 | Cancel unpaid order allowed | P1 | Unpaid order | Cancel succeeds with permissions | TODO |
| UAT-010 | Cancel paid order blocked | P0 | Paid order | Cancel blocked with finance-safe error | TODO |
| UAT-011 | Refund request on eligible order | P0 | Completed paid order | Refund transaction created | TODO |
| UAT-012 | Refund blocked on ineligible status | P0 | Pending/rejected order | Refund blocked | TODO |
| UAT-013 | Refund blocked over amount | P0 | Paid order | Validation error shown | TODO |
| UAT-014 | One-refund lock enforced | P0 | Already refunded order | Further refund blocked | TODO |
| UAT-015 | Refund chip shows processed only | P0 | Mixed refund rows | Count/amount only from `processed` rows | TODO |
| UAT-016 | Collections queue due today | P1 | Credit/advance orders | Correct segmenting to due today | TODO |
| UAT-017 | Collections queue overdue | P1 | Overdue credit orders | Correct overdue counts | TODO |
| UAT-018 | Credit collection settlement | P0 | Credit order | Payment settles to `paid`; GL posted | TODO |
| UAT-019 | Financial lock prevents unsafe edit | P0 | Finance-locked order | Unsafe mutation blocked | TODO |
| UAT-020 | Revision submit (upgrade) | P1 | Editable order | Revision pending created | TODO |
| UAT-021 | Revision submit (downgrade) | P1 | Editable order | Revision pending created with diff | TODO |
| UAT-022 | Revision confirm upgrade GL | P1 | Pending upgrade revision | GL adjustment revenue posted | TODO |
| UAT-023 | Revision confirm downgrade refund | P1 | Pending downgrade/refund | GL downgrade refund posted | TODO |
| UAT-024 | Revision confirm downgrade store_credit | P1 | Pending downgrade/store_credit | Credit wallet entry posted | TODO |
| UAT-025 | Payment split two methods | P0 | Balance due order | Two payment txns + GL postings created | TODO |
| UAT-026 | Payment split overpay blocked | P0 | Balance due order | Overpayment rejected | TODO |
| UAT-027 | Sales register finance-safe view | P1 | Data seeded | Rows and totals match filters | TODO |
| UAT-028 | Refunds view | P1 | Refund data exists | Pending/processed tabs accurate | TODO |
| UAT-029 | Daily close balance check | P0 | Ledger entries present | Debits == credits gate enforced | TODO |
| UAT-030 | Cross-report tie-out sample | P0 | Same date range | Sales register matches ledger totals | TODO |
| UAT-031 | Admin permission gate order_edit | P0 | User without permission | Update blocked 403 | TODO |
| UAT-032 | Admin permission gate order_refund | P0 | User without refund perm | Refund blocked 403 | TODO |
| UAT-033 | Timeline integrity | P1 | Status changes made | Timeline ordered and complete | TODO |
| UAT-034 | API status async update | P1 | Auth admin session | Returns updated status/payment snapshot | TODO |
| UAT-035 | Historical segment empty after purge | P2 | Old orders deleted | Historical tab shows 0 for filters | TODO |

## Execution Notes
- Capture evidence per scenario in `storage/recovery` and append outcome links.
- Re-run high-risk cases (UAT-002, 007, 011, 015, 019, 025, 030) after each Phase 1 code update.
