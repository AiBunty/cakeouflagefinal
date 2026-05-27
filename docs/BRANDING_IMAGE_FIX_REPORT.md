# BRANDING IMAGE FIX REPORT

Date: 2026-05-26
Objective: Centralize business logo fallback behavior across shared views.

## Implemented Architecture
- Added `BusinessBrandingService` to normalize and resolve branding assets.
- Service resolves:
  - `navbar_logo_url`
  - `footer_logo_url`
  - `business_logo`
  - `favicon_url`
  - fallback and `onerror` snippets
- Added compatibility helper file: `includes/business-branding.php`.

## View Integration
- `View::render()` now builds branding asset map and injects it into `siteConfig`.
- Existing keys are synchronized for backward compatibility.

## Updated Rendering Points
- Head favicon now uses branding fallback data.
- Header logo now uses centralized logo + onerror fallback.
- Footer logo now uses centralized logo + onerror fallback.
- Mobile menu logo now uses centralized logo + onerror fallback.
- Login and register pages now use resolved branding logo fallback.
- Category mobile header logo now uses resolved branding logo fallback.

## Hardening Notes
- Unsafe logo values (for example `javascript:`) are rejected.
- Relative paths are normalized to rooted paths.
- Empty values collapse to stable defaults.
