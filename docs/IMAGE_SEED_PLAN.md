# IMAGE_SEED_PLAN

## Goal
Populate only missing product media safely, without overwriting valid existing image assignments.

## One-Time Repair Endpoint
Use the secured one-time script:

- Path: `/__repair_product_images.php`
- Required key: `k=<IMAGE_REPAIR_KEY>`
- Optional dry run: `dry_run=1`

Example:
- Dry run: `/__repair_product_images.php?k=YOUR_KEY&dry_run=1`
- Execute: `/__repair_product_images.php?k=YOUR_KEY`

## Safe Update Rules
1. Only update `products.featured_image` when it is `NULL` or empty.
2. Only insert into `product_images` when product has zero valid gallery rows.
3. Never overwrite non-empty existing `featured_image` values.
4. Script is idempotent and safe to run multiple times.

## Placeholder Mapping
Category slug to placeholder path (examples):

- `classic-cakes` -> `/client/assets/images/placeholders/classic-cakes.svg`
- `cheesecakes` -> `/client/assets/images/placeholders/cheesecakes.svg`
- `dessert-cakes` -> `/client/assets/images/placeholders/dessert-cakes.svg`
- `tart-cakes` -> `/client/assets/images/placeholders/tart-cakes.svg`
- `tea-cakes-travel-cakes` -> `/client/assets/images/placeholders/travel-cakes.svg`
- `birthday-cakes` -> `/client/assets/images/placeholders/birthday-cakes.svg`
- `anniversary-cakes` -> `/client/assets/images/placeholders/anniversary-cakes.svg`
- `engagement-wedding-cakes` -> `/client/assets/images/placeholders/wedding-cakes.svg`
- `gifting` -> `/client/assets/images/placeholders/gifting.svg`
- default -> `/client/assets/images/placeholders/product-generic.svg`

## Global Runtime Fallback
All product images now resolve through server-side helper:
- `product_image_url(path, categorySlug)`

If assigned file is missing/unreadable, helper falls back immediately to category placeholder, then global placeholder.

## Re-run Checklist
1. Upload placeholder assets.
2. Upload `__repair_product_images.php`.
3. Run dry-run URL and verify counts.
4. Run execute URL.
5. Spot-check `/shop`, `/category/*`, `/product/*`.
