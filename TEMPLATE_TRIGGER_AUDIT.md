# Template-Trigger Coverage Matrix (May 2026)

## Event Aliases (OrderAutomationService)

| Logical Trigger                | Aliases (event_key)                                  |
|-------------------------------|-----------------------------------------------------|
| online_order_received_customer | order_created, order_placed_customer                 |
| online_order_received_admin    | admin_new_order                                     |
| manual_order_received_customer | manual_order_customer, order_created_manual_customer |
| manual_order_received_admin    | manual_order_admin, admin_new_order_manual           |
| payment_confirmed_customer     | order_confirmed_customer                            |
| payment_confirmed_admin        | admin_payment_confirmed                             |
| ready_order_customer           | order_in_preparation, order_ready_for_pickup         |
| ready_order_admin              | admin_order_ready                                   |
| order_delivered_customer       | order_delivered                                     |
| order_delivered_admin          | admin_order_delivered                               |
| reject_order_customer          | order_rejected, reject_order                        |
| reject_order_admin             | admin_order_rejected                                |
| follow_up_review_email         | follow_up_review_customer, follow_up_reminder        |
| annual_reorder_email           | follow_up_yearly_customer, follow_up_yearly         |

## Seeded Templates (database/seed.sql)

| Channel   | Event Key              | Subject                              | Active |
|-----------|------------------------|--------------------------------------|--------|
| email     | order_created          | Your Cakeouflage order is confirmed  | 1      |
| email     | payment_overdue        | Payment due reminder                 | 1      |
| whatsapp  | order_created          | (none)                               | 1      |
| whatsapp  | order_ready_for_pickup | (none)                               | 1      |

## Coverage Gaps

- No seeded template for: payment_confirmed, order_delivered, reject_order, follow_up_review, annual_reorder, admin_* events, OTP.
- WhatsApp: Only order_created and order_ready_for_pickup present.
- OTP: Sent via MailService, not using template system.
- Branding: Footer uses static logo, not adaptive.

## Next Steps
- Add missing templates for uncovered triggers.
- Refactor OTP to use template system.
- Update branding for adaptive DCore logo.
