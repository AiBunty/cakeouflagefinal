# Order to Invoice to Refund Flow (Final)

Date: 2026-05-24
Status: Confirmed via step-by-step Q&A

## Confirmed Decisions

1. Payment shortfall handling
- Decision: Allow both paths per order.
- Path A: Keep shortfall as balance due (AR follow-up queue).
- Path B: Book shortfall as discount with mandatory reason.

2. Invoice generation trigger
- Decision: Generate invoice only when payment status is `paid`.

3. Fulfilled order visibility
- Decision: Delivered/completed orders must remain visible in both Operational and Historical views until archived.

4. Refund basis
- Decision: Refund from amount actually collected only.
- Rule: Refund can never exceed net collected amount.

5. Refund approvals
- Decision: Mandatory two-person control.
- Rule: One admin requests, a different authorized admin approves/processes.

6. Invoice state after full refund
- Decision: Keep invoice immutable, mark invoice/payment state as Refunded.
- Rule: Never delete invoice record for refunded orders.

7. Discount control threshold
- Decision: Manager override mandatory when shortfall-discount exceeds 5% of grand total.

8. Delivery closure semantics
- Decision: Delivered action remains single final action and maps to Completed.

## Final Operational Flow

1. Order Created
- Status: `pending_payment`
- Payment: `pending`

2. Payment Confirmation
- Admin enters received amount.
- If received == grand total:
  - Payment -> `paid`
  - Collection -> `fully_paid`
  - Balance due -> 0
- If received < grand total:
  - Admin chooses one path:
    - AR Path: keep `balance_due_amount` > 0 and queue collection follow-up.
    - Discount Path: write discount reason, apply discount, and if discount > 5% require manager override.

3. Invoice Eligibility
- Invoice can be generated only after payment becomes `paid`.
- Invoice remains immutable audit artifact.

4. Production/Execution
- Confirmed -> Preparing -> Ready/Out-for-delivery as applicable.

5. Delivery Finalization
- Admin presses Delivered.
- System maps to `completed` as canonical final status.
- Order remains visible in Operational + Historical until archived.

6. Refund Handling
- Refund request is created against collected amount.
- Dual-control approval required.
- On processing:
  - Payment/order refund statuses updated.
  - Financial transactions and audit logs recorded.
  - Invoice status moves to Refunded state (no deletion).

## Accounting Integrity Rules

- `refund_total <= net_collected_amount`
- No destructive deletion of invoice artifacts.
- Every discount override must have reason.
- >5% discount requires manager override record.
- Refund processing requires separate approver identity.

## Verification Notes (This Session)

- Delivered-hide bug fixed: fulfilled statuses included in Operational segment.
- Historical visibility includes fulfilled statuses as confirmed.
- 3-dot overflow menu clipping fixed with higher z-index, overflow visibility, and right-opening alignment.
- Old order-linked data purged from local DB for clean testing.
- Fresh paid+confirmed test order created and invoice page verified as rendering successfully.
