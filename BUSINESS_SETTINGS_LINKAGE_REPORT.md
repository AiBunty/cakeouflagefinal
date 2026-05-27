# BUSINESS_SETTINGS_LINKAGE_REPORT

## Objective
Validate that communications, invoice, frontend, and accounting layers consume centralized business settings.

## Governed Keys
- `business_logo`
- `business_name`
- `support_email`
- `support_phone`
- `support_whatsapp`
- `business_address`
- `currency_code`
- `currency_symbol`

## Database Evidence
Latest DB check confirms keys exist and are populated:
- `business_logo`: `/public/uploads/originals/branding/mainlogo_20260525182236_5b50adbc.png`
- `business_name`: `Cakeouflage`
- `support_email`: `cakouflage@gmail.com`
- `support_phone`: `+919898284900`
- `support_whatsapp`: `+919898284900`
- `business_address`: `Pathardi, Near raddsion, Nashik, maharshtra`
- `currency_code`: `INR`
- `currency_symbol`: `Rs`

## Linkage Implementation
1. Settings UI + save path:
   - `admin/business-settings.php`
   - `admin/save-business-settings.php`
   - Added fields and persistence for business logo/address/currency
2. Backfill migration:
   - `database/migrations/2026-05-25-business-settings-registry-backfill.sql`
3. Email/communications branding injection:
   - `app/Services/EmailBrandingService.php`
4. Invoice currency linkage:
   - `admin/includes/business-settings-helper.php`
   - `admin/includes/invoice_helpers.php`
5. Frontend config propagation:
   - `app/Core/View.php`
   - Currency symbol passed through `siteConfig`
6. Frontend usage updates:
   - `app/Views/pages/product.php`
   - `app/Views/pages/category.php`
7. Accounting report usage update:
   - `admin/sales_register.php`

## Hardcode Cleanup Checks
1. Active template deprecated amount token: `{{actual_received_amount}}` -> `0` references.
2. Active template hardcoded support email (`support@cakeouflage`) -> `0` references.
3. Currency now sourced from settings in invoice and sales register rendering paths.

## Verdict
PASS - Business settings now act as a centralized source for communications branding/contact and currency-driven displays in key invoice/frontend/accounting paths.
