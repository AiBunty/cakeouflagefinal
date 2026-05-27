# VARIABLE_LINKAGE_AUDIT

## Scope
Communications variable integrity audit covering source -> context -> resolver -> renderer linkage.

## Architecture Linkage (Implemented)
1. Source of truth registry: `app/Services/TemplateVariableRegistry.php`
2. Runtime resolver: `app/Services/VariableResolverService.php`
3. Render path: `app/Core/QueueWorker.php` (`renderStrict` path)
4. Context construction: `app/Services/OrderAutomationService.php`
5. Branding/business context: `app/Services/EmailBrandingService.php`
6. Editor exposure: `admin/communications.php` (CV panel + TinyMCE merge tags from registry)

## Verified Variable Linkage Matrix
| Variable | Source | Context Injection | Resolver | Renderer | Status |
|---|---|---|---|---|---|
| `customer_name` | `orders.customer_name` | `OrderAutomationService::buildOrderContext` | Registry + resolver | QueueWorker strict render | PASS |
| `first_name` | derived from customer name | `buildOrderContext` | resolver fallback split | QueueWorker strict render | PASS |
| `customer_email` | `orders.customer_email` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `customer_phone` | `orders.customer_phone` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `order_number` | `orders.order_number` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `order_status` | `orders.order_status` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `fulfillment_status` | `orders.order_status` alias | `buildOrderContext` | alias-aware registry | QueueWorker strict render | PASS |
| `delivery_date` | `orders.delivery_date` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `product_summary` | item summary aggregate | `buildOrderContext` | registry + alias (`item_names`) | QueueWorker strict render | PASS |
| `payment_status` | `orders.payment_status` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `payment_method` | `orders.payment_method` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `transaction_reference` | txn fields chain | `buildOrderContext` | alias chain in registry/resolver | QueueWorker strict render | PASS |
| `payment_received_amount` | `orders.grand_total` fallback | `buildOrderContext` | registry fallback to `grand_total` | QueueWorker strict render | PASS |
| `coupon_code` | `orders.coupon_code` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `coupon_discount` | `orders.discount_total` | `buildOrderContext` | registry alias chain | QueueWorker strict render | PASS |
| `grand_total` | `orders.grand_total` | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `refund_amount` | refund log/context | event payload context | resolver + registry | QueueWorker strict render | PASS |
| `refund_reason` | refund log/context | event payload context | resolver + registry | QueueWorker strict render | PASS |
| `refund_status` | order/refund status | `buildOrderContext` + refund contexts | registry | QueueWorker strict render | PASS |
| `business_logo` | `settings.business_logo` (synced with email logo) | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `business_name` | `settings.business_name` | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `support_email` | `settings.support_email` | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `support_phone` | `settings.support_phone` | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `support_whatsapp` | `settings.support_whatsapp` | `EmailBrandingService` | registry fallback to phone | QueueWorker strict render | PASS |
| `business_address` | `settings.business_address` / composed address | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `currency_symbol` | `settings.currency_symbol` | `EmailBrandingService` | registry | QueueWorker strict render | PASS |
| `delivery_slot` | slot label chain | `buildOrderContext` | registry fallback chain | QueueWorker strict render | PASS |
| `delivery_method` | fulfillment mode chain | `buildOrderContext` | registry alias chain | QueueWorker strict render | PASS |
| `delivery_address` | order delivery lines | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `invoice_number` | order/invoice chain | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `invoice_date` | order created/invoice date | `buildOrderContext` | registry | QueueWorker strict render | PASS |
| `invoice_download_link` | computed order invoice URL | `buildOrderContext` | registry | QueueWorker strict render | PASS |

## Deprecated/Non-Working Cleanup Results
1. Active template token scan: `TOTAL_TOKENS=451`
2. Unknown tokens after registry + compatibility mapping: `UNKNOWN_COUNT=0`
3. Deprecated token references in active templates:
   - `{{actual_received_amount}}`: `0`

## Notes
- Compatibility-only tokens are still supported at runtime (not exposed in editor UI) to prevent regression for legacy templates.
- Editor variable panel now reads directly from the centralized registry, enforcing verified-only exposure.
