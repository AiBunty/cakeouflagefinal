# Veg Filter, Homepage CTA, and BYOC Update TODOs

## Scope Guard

- Create all implementation changes only after this checklist exists.
- Do not hardcode Veg/Non-Veg behavior in templates, controllers, or queries.
- Keep category, product, search, and BYOC flows backward compatible while moving dietary behavior behind centralized business settings.
- Reuse existing business settings loading patterns; do not duplicate settings resolution logic.

## 1. Homepage CTA Fixes

- Identify homepage card components and static/mobile CTA render paths for Wedding Cakes, Birthday & Anniversary, Baby Shower, and Corporate Cakes.
- Replace current `/custom-cake-inquiry` targets with `/category` for every Explore CTA.
- Verify both desktop and mobile CTA variants share the same target source of truth.
- Preserve hover animations, card layout, and existing CTA styling.
- Check for stale hardcoded absolute URLs in templates, scripts, and client-side hydration.

## 2. Business Settings Dietary Architecture

- Locate current business settings storage and admin UI render/update flow.
- Add centralized setting key `store_food_mode` with allowed values `veg_only` and `veg_nonveg`.
- Set default/fallback behavior to `veg_only` only when setting is missing and document fallback explicitly in helper/service.
- Ensure settings read/write passes through existing business settings persistence layer.
- Add migration or safe bootstrap path for existing environments.

## 3. Product Dietary Tagging

- Add product-level dietary field architecture using `dietary_type` with values `veg` and `nonveg`.
- Default products to `veg` in schema and service-layer fallbacks.
- Map existing product payloads/admin forms/imports so missing values resolve safely.
- Ensure `veg_only` mode forces/normalizes products to `veg` without breaking old records.
- Keep legacy `dietary_tag` behavior intact where still used for eggless/vegan/sugar-free semantics.

## 4. Frontend Dietary Filtering

- Create centralized dietary visibility decision path from business settings.
- Add Veg / Non-Veg filter UI only when `store_food_mode = veg_nonveg`.
- Hide all dietary mode filters when `store_food_mode = veg_only`.
- Keep category filtering logic compatible with existing category/search query handling.
- Verify PDP, search results, recommendations, and product cards consume the same dietary mode helper.

## 5. BYOC Form Cleanup

- Remove `Diet Preference` field from Build Your Own Cake frontend form.
- Remove `Budget Range` field from Build Your Own Cake frontend form.
- Remove server-side validation for both fields.
- Remove DB processing/payload mapping for both fields.
- Remove admin email payload references for both fields.
- Remove WhatsApp payload references for both fields if used.
- Preserve uploads, notes, and current submission success flow.

## 6. DB Schema Changes

- Add/verify `products.dietary_type` column with safe default `veg`.
- Add/verify business settings persistence for `store_food_mode`.
- Make schema change idempotent for local/prod environments.
- Review whether BYOC storage tables contain obsolete dietary/budget columns and decide whether to stop writing first versus destructive cleanup later.

## 7. Admin UI Updates

- Add Store Type radio controls to Business Settings admin UI.
- Update business settings save endpoint to persist `store_food_mode`.
- Update admin product form to show Dietary Type only in `veg_nonveg` mode.
- Ensure product create/edit API and legacy forms both respect dietary setting visibility and fallback rules.
- Confirm admin UX does not expose Non-Veg controls in `veg_only` mode.

## 8. Category Filter Logic

- Update category page controller/query layer to include dietary filtering only when enabled by store mode.
- Ensure `veg_only` mode hides filter controls and treats products as veg.
- Ensure `veg_nonveg` mode supports filtering without breaking existing category, search, and sort behavior.
- Verify badges/dots are optional but driven by centralized logic.

## 9. Validation and Testing Checklist

- Homepage Explore CTAs route to `/category` on desktop.
- Homepage Explore CTAs route to `/category` on mobile.
- Business Settings toggles persist and reload correctly.
- `veg_only` mode hides Non-Veg UI and forces veg behavior.
- `veg_nonveg` mode shows dietary controls and filters correctly.
- Product create/update persists `dietary_type` correctly.
- Category/search/PDP/recommendations use centralized dietary mode behavior.
- BYOC form submits successfully with removed fields absent.
- Import/export handles Dietary Type column correctly.
- Legacy compatibility remains intact for existing products and filters.

## 10. Deployment Checklist

- Review all changed files for homepage, business settings, product admin, category/search, BYOC, and import/export.
- Run local lint/syntax validation for touched PHP/JS files.
- Run local manual/API checks for homepage CTAs, settings toggles, dietary filtering, and BYOC submission.
- Execute deployment audit using `deploy-serverbyt.ps1` flow: audit, dry run, upload changed files, post-deploy validation.
- Validate homepage, category filters, product admin, and BYOC after deploy.

## Implementation Order

1. Map homepage CTA source files and BYOC files.
2. Map business settings source of truth and admin update flow.
3. Add dietary schema/settings helpers.
4. Update admin product/business settings UI and save logic.
5. Update frontend category/search/PDP dietary behavior.
6. Remove BYOC fields end-to-end.
7. Update import/export for dietary type.
8. Run local validation.
9. Prepare deployment-safe file set.