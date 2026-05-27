# Communication Todo Completion (2026-05-25)

## Completed Items

1. Patch template seeding and editor variables
- `admin/communications.php` now uses shared `render_master_email_layout()`.
- Added editor variables for `{{fulfillment_status}}` and `{{coupon_discount}}` in both custom variable panel and TinyMCE merge tags.

2. Expand order context and resolver fallbacks
- `app/Services/OrderAutomationService.php` now includes additional aliases:
  - `actual_received_amount`
  - `fulfilment_status`
  - `fulfilment_mode` and `fulfillment_mode`
  - broader `transaction_reference` fallback chain
  - broader `delivery_slot` fallback chain
- `app/Services/VariableResolverService.php` now resolves:
  - legacy `actual_received_amount`
  - `fulfillment_status` and `fulfilment_status`
  - `delivery_slot` fallback aliases
  - robust `transaction_reference` aliases
  - derived `support_whatsapp_url` from support contact when missing.

3. Add support WhatsApp settings plumbing
- Existing settings save path already persists `support_whatsapp`.
- Added migration `database/migrations/2026-05-25-support-whatsapp-backfill.sql` to auto-backfill setting from `support_phone` if missing.

4. Add master email layout and migration
- Shared layout exists at `app/Views/email/master-layout.php` and is now wired into communications template builder.
- Existing migration `database/migrations/2026_replace_actual_received_amount.sql` retained for deprecated token replacement.

5. Create required communication docs
- This completion document added for implementation and validation traceability.

## Validation Snapshot
- PHP lint PASS:
  - `admin/communications.php`
  - `app/Services/OrderAutomationService.php`
  - `app/Services/VariableResolverService.php`
  - `app/Services/EmailBrandingService.php`
- DB checks:
  - Required communication template keys: present.
  - Deprecated token `{{actual_received_amount}}` rows: 0.
  - `support_whatsapp` setting: migration provided to ensure presence.
