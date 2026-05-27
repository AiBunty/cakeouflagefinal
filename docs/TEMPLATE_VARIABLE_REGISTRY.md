# Template Variable Registry

Date: 2026-05-25

## Branding Variables
- {{email_logo_url}}
- {{business_name}}
- {{brand_primary_color}}
- {{brand_secondary_color}}
- {{support_email}}
- {{support_phone}}
- {{support_whatsapp}}
- {{support_whatsapp_url}}

## Customer Variables
- {{customer_name}}
- {{first_name}}
- {{customer_email}}
- {{customer_phone}}
- {{company_name}}

## Order and Payment Variables
- {{order_id}}
- {{order_number}}
- {{order_status}}
- {{fulfillment_status}}
- {{payment_status}}
- {{payment_method}}
- {{transaction_reference}}
- {{item_names}}
- {{item_count}}
- {{grand_total}}
- {{payment_received_amount}}
- {{subtotal}}
- {{tax_total}}
- {{coupon_discount}}
- {{discount_amount}}
- {{coupon_code}}
- {{delivery_date}}
- {{delivery_slot}}
- {{delivery_method}}
- {{delivery_address}}
- {{upi_link}}

## Refund Variables
- {{refund_amount}}
- {{refund_reason}}
- {{refund_type}}
- {{refund_notes}}
- {{refund_reference}}
- {{total_refunded}}
- {{remaining_sales_amount}}

## Invoice and BYOC Variables
- {{invoice_number}}
- {{invoice_amount}}
- {{due_date}}
- {{quote_number}}
- {{quote_amount}}
- {{quote_description}}

## Contact.* Variables For CRM Compatibility
- {{contact.name}}
- {{contact.first_name}}
- {{contact.mobile}}
- {{contact.phone}}
- {{contact.email}}
- {{contact.orderid}}
- {{contact.item}}
- {{contact.amount}}
- {{contact.upi_link}}

## Deprecation Rule
- Deprecated: {{actual_received_amount}}
- Canonical replacement: {{payment_received_amount}}
- Migration added in database/migrations/2026_replace_actual_received_amount.sql
