# VERIFIED_VARIABLE_REGISTRY

## Registry Source of Truth
- File: `app/Services/TemplateVariableRegistry.php`
- UI exposure and TinyMCE merge tags are generated directly from this registry.

## Exposed (Verified) Variables

### Customer
- `customer_name`
- `first_name`
- `customer_email`
- `customer_phone`

### Order
- `order_number`
- `order_status`
- `fulfillment_status`
- `delivery_date`
- `product_summary`

### Payment
- `payment_status`
- `payment_method`
- `transaction_reference`
- `payment_received_amount`

### Coupon
- `coupon_code`
- `coupon_discount`
- `grand_total`

### Refund
- `refund_amount`
- `refund_reason`
- `refund_status`

### Business
- `business_logo`
- `business_name`
- `support_email`
- `support_phone`
- `support_whatsapp`
- `business_address`
- `currency_symbol`

### Delivery
- `delivery_slot`
- `delivery_method`
- `delivery_address`

### Invoice
- `invoice_number`
- `invoice_date`
- `invoice_download_link`

## Runtime Compatibility (Hidden from Editor)
The following legacy tokens are still resolved at runtime for backward compatibility but intentionally not exposed in editor UI:
- `quote_number`, `quote_amount`, `quote_description`, `quote_accept_link`
- `inquiry_id`, `advance_amount`, `budget_range`, `design_brief_notes`
- `diet_preference`, `event_date`, `event_information`, `number_of_servings_guests`
- `refund_type`, `refund_reference`, `total_refunded`, `remaining_sales_amount`
- `otp_code`, `otp_expiry`, `reset_link`, `profile_link`, `phone_country_code`
- `google_review_link`, `last_order_month`, `invoice_html`

## Alias/Normalization Rules
- `actual_received_amount` -> `payment_received_amount`
- `fulfilment_status` -> `fulfillment_status`
- `fulfilment_mode` -> `delivery_method`
- `discount_amount` -> `coupon_discount`
- `item_names` -> `product_summary`
- `email_logo_url` -> `business_logo`
- `currency` -> `currency_symbol`
- `remaining_balance` -> `remaining_sales_amount`

## Registry Audit Result
- Total active template tokens scanned: `451`
- Unknown tokens after registry + compatibility mapping: `0`

## Verdict
PASS - Registry is centralized, verified, and actively enforced in both editor exposure and strict runtime rendering.
