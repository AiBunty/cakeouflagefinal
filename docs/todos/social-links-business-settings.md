# Social Links Through Business Settings

## Objective
Centralize all social/contact/floating action links through Business Settings so admin can manage them from one place.

## TODOs

1. Footer social integration
- Replace static Facebook/Instagram/WhatsApp links with dynamic values from Business Settings.
- Use `https://wa.me/{number}` for WhatsApp.
- Add graceful fallback (hide icon or disable safely) when values are missing.

2. Floating action integration
- Ensure all floating actions are dynamic:
  - Call -> `tel:{contact_phone}`
  - WhatsApp -> `https://wa.me/{whatsapp_number}`
  - Instagram -> `instagram_url`
  - Google Location -> `google_maps_url`
- Add Google Maps floating button with consistent styling.
- Validate responsive behavior and stacking order.

3. WhatsApp enquiry flow
- Build product-page WhatsApp enquiry URL from settings + contextual message.
- Message template:
  - Hello,
  - I want this cake: {Product Name}
  - Product Link: {Current Product URL}
  - I want to know more details about this cake.
- Ensure URL encoding and mobile/desktop compatibility.

4. Business settings DB updates
- Add/confirm setting keys:
  - `facebook_url`
  - `instagram_url`
  - `whatsapp_number`
  - `google_maps_url`
  - `contact_phone`
  - `support_email`
- Preserve existing WhatsApp field behavior.
- Add migration/backfill for missing keys.

5. Helper architecture
- Create/extend centralized helper/service for business settings retrieval and normalization.
- Reuse same source for footer, floating actions, product enquiry, and contact page.
- Avoid duplicated social config logic.

6. Google Maps integration
- Add dynamic Google Maps link usage in floating action and contact contexts.
- Validate URL format and safe fallback behavior.

7. Responsive validation
- Verify desktop/tablet/mobile for:
  - Footer icon alignment
  - Floating button spacing/overlap
  - WhatsApp enquiry behavior

8. Deployment checklist
- Run local validation for all updated modules.
- Run syntax checks and targeted behavior checks.
- Deploy safely with `deploy-serverbyt.ps1` flow:
  1. Audit
  2. Dry run
  3. Upload changed files
  4. Post-deploy validation
