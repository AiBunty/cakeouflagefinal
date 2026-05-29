# Product Master Excel Redesign TODO

Status: In Progress  
Owner: Catalog/ERP Track  
Master Principle: Excel import file is the external master of truth. Internal storage remains normalized relational.

## 1. Excel Architecture Redesign
- [x] Freeze official matrix Excel contract (one row per product).
- [ ] Finalize base metadata columns:
  - Product Name
  - Category
  - SubCategory
  - Description
  - Food Type
  - Dietary Tag
  - Chef's Special
  - Enable Topper Selection
  - Enable Note on Cake
- [x] Generate dynamic size columns from size master (instead of fixed hardcoded size headers).
- [ ] Add schema version marker to template/export for compatibility checks.
- [ ] Publish import/export contract doc and examples for ops team.

## 2. Import Pipeline Redesign
- [x] Build matrix parser: one row => product + many size price variants.
- [x] Detect dynamic size columns by matching active `product_size_master.label`.
- [ ] Upsert strategy:
  - Existing product => update metadata + variant matrix
  - Missing product => create metadata + variants
- [x] Empty size cell rule => disable/not create that variant.
- [x] Add preview phase before commit (new/updated/invalid rows).
- [ ] Add strict mode + safe mode behavior flags.

## 3. Version History System
- [x] Create/confirm `product_import_versions` table and storage metadata.
- [x] Register every commit import as immutable version event.
- [x] Attach source file path, uploader, run summary, and snapshot pointers.
- [x] Store quick summary stats: total rows, created, updated, archived, failed.
- [x] Keep latest 5 versions only (retention worker / post-import cleanup).

## 4. Restore Workflow
- [x] Add admin Version History screen with list + details.
- [x] Add Restore action with confirmation guardrail.
- [x] Restore must be transactional and all-or-nothing.
- [ ] Restore scope:
  - Products
  - Variants
  - Pricing
  - Category/SubCategory links
- [ ] Write audit logs for restore operations.
- [ ] Add post-restore verification and rollback point creation.

## 5. Dynamic Size Columns
- [x] Create `product_size_master` table:
  - id
  - label
  - sort_order
  - is_active
- [x] Seed current operational sizes (Per Pcs, 0.5 kg, 1 kg, ...).
- [x] Build admin management for size master (add/disable/reorder).
- [x] Ensure export header is generated from active sizes sorted by `sort_order`.
- [x] Ensure import recognizes future sizes without code change.

## 6. Product Admin Redesign
- [ ] Align Add/Edit product form with Excel matrix UX.
- [ ] Render dynamic size-price grid from `product_size_master`.
- [ ] Preserve feature toggles:
  - Chef's Special
  - Enable Topper Selection
  - Enable Note on Cake
- [ ] Remove base-price-only operational bottleneck.
- [ ] Ensure edit view loads full matrix and supports partial updates.

## 7. Variant Mapping Engine
- [ ] Build mapper from matrix cells -> `product_variants` rows.
- [ ] Keep internal relational integrity (product to variants).
- [ ] Guarantee deterministic variant identity per size.
- [ ] Enforce one default variant selection strategy.
- [ ] Avoid destructive hard-delete patterns that break references.

## 8. SKU Automation
- [ ] Remove manual SKU entry requirement from import/admin.
- [ ] Implement SKU service using category + product + size + sequence.
- [ ] Rules:
  - Unique
  - Stable
  - Deterministic
  - Safe regeneration only on identity change
- [ ] SKU optional on export (ops visibility), hidden on import requirement.

## 9. Validation Rules
- [ ] Validate category/subcategory existence.
- [ ] Validate duplicate product rows and collision keys.
- [ ] Validate numeric prices and non-negative values.
- [ ] Validate unknown size columns (hard error unless mapped).
- [ ] Validate boolean/toggle values.
- [ ] Return row-level actionable error reports + failed-row file.

## 10. Rollback Plan
- [ ] Pre-import snapshot creation mandatory.
- [ ] Fast rollback command from latest safe version.
- [ ] Restore previous version if import commit fails post-check.
- [ ] Keep forensic logs for import + rollback action chain.

## 11. Migration Strategy
- [ ] Backfill current fixed size variants into `product_size_master` labels.
- [ ] Migrate legacy import/export endpoints to new matrix contract.
- [ ] Maintain temporary compatibility bridge for existing admin workflows.
- [ ] Run data migration dry-runs on local snapshot and staging copy.
- [ ] Cutover plan with toggle and fallback path.

## 12. Deployment Checklist
- [ ] Local validation complete:
  - [x] Export old-style matrix exactness
  - [x] Import create/update matrix
  - [x] Version history + restore
  - [x] Admin matrix UI add/edit
- [ ] Performance check on large workbook imports.
- [ ] Accounting compatibility check (historical orders unaffected).
- [ ] Smoke tests for product APIs/shop rendering.
- [ ] Deployment via `deploy-serverbyt.ps1` only after sign-off.

## Execution Note
No implementation proceeds without this TODO document in place. This file is now the authoritative execution checklist for the product master Excel redesign stream.
