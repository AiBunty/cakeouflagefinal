# Upload Pipeline Fix Report

**Date:** 2026-05  
**Scope:** Product image upload, branding logo upload, image persistence, DB save reliability, upload validation, filesystem write logic  
**Constraint:** Categories upload flow left untouched (reference implementation)

---

## Summary of Root Causes Found

| # | Root Cause | Symptom | Severity |
|---|-----------|---------|----------|
| 1 | `brandingUpload()` targeted `uploads/branding/` — a Docker bind-mount dead-end, always empty | "Unable to save logo file" on every logo upload | Critical |
| 2 | `products.php` emitted `updated=0` when category dropdown failed JS pre-select → `$categoryId=0` → FK violation on NOT NULL col | Silent product save failure | Critical |
| 3 | `ensureDirectory()` used `0775` with no chmod on existing dirs | New directories in Docker sometimes not writable | High |
| 4 | Inline image upload code in `products.php` + `add-product.php` had no centralised validation, no audit logging | Security gaps; hard to diagnose failures | High |
| 5 | SQL injection in `add-product.php` INSERT + slug-check + variant INSERT | OWASP A03 — Injection | Critical |

---

## Files Modified

### `app/Controllers/AdminApiController.php`

**Phase 1 changes:**
- `brandingUpload()` — `$brandingDir` changed from `uploads/branding/` → `public/uploads/branding/` (same subtree as working `banners.php` uploads, confirmed writable in Docker bind-mount)
- All four `$targetRelative` strings updated to `/public/uploads/branding/…`
- Old-file cleanup guard updated to match new path prefix
- `ensureDirectory()` — mode changed from `0775` → `0777` + `@chmod($path, 0777)` applied even when dir already exists

**Phase 3 wiring:**
- Replaced inline SVG copy + raster `optimizeImageToWebp`/`move_uploaded_file` logic in `brandingUpload()` with `\App\Services\ImageUploadService::upload()` call (SVG allowed, 2 MB limit, `base_name` token passed through)

---

### `admin/products.php`

**Phase 2 changes:**
- Server-side category fallback: if `$categoryId <= 0 && $id > 0`, re-reads `collection_category_id` from DB so NOT NULL FK is always satisfied
- `$newImagePath` initialised as `''` (not `null`) to prevent empty-string-in-NULL-column issues
- `$uploadWarning` variable added; image upload failure surfaces as amber flash banner (`uploadwarn=1` query param) instead of silently succeeding with `updated=1`
- `execute()` error now logged via `error_log` on failure
- Flash HTML updated to 3-branch: success / success-with-image-warn / failure

**Phase 3 wiring:**
- `require_once '../app/Services/ImageUploadService.php'` added at top
- Inline image1 and image2 upload blocks replaced with `ImageUploadService::upload()` calls

**Phase 5 — UI feedback:**
- `<form>` given `id="prodEditForm"`
- `.prod-editor-save.is-saving` CSS state added (opacity 0.65, `cursor:not-allowed`)
- JS submit listener added: on form submit, save button is disabled and text changes to "Saving…" (prevents double-submit; re-enabled automatically when page navigates away)

---

### `admin/add-product.php`

**Phase 4 — SQL injection fixes (OWASP A03):**
- Slug uniqueness check: raw `"SELECT id FROM products WHERE slug = '$slug'"` → `safePrepare` + `bind_param`
- INSERT: raw multi-variable string interpolation → `safePrepare` + 14-param `bind_param('ssssiiissisiii', …)`  
  - Removed `$child_id_value`, `$subcategory_id_value`, `$collection_id_value` PHP string-building hack; nullable PHP ints passed directly via `bind_param 'i'`
  - `$base_price_str = (string)(float)$base_price` used for safe decimal binding
- Product variant INSERT: raw `$conn->query("INSERT … '$product_id'")` → `safePrepare` + `bind_param('isssi', …)`
- Error output: raw `echo "Error: " . $conn->error` removed; replaced with `error_log` + safe user-facing `alert`

**Phase 3 wiring:**
- `require_once '../app/Services/ImageUploadService.php'` added at top
- Inline image1 and image2 upload blocks replaced with `ImageUploadService::upload()` calls

---

### `app/Services/ImageUploadService.php` (new file)

Centralised, reusable upload service with:

- **Security checks** (in order): PHP upload error code, dangerous extension block (`php`, `phtml`, `phar`, `pl`, `py`, `rb`, `cgi`, `sh`, `exe`, `bat`, `cmd`), MIME detection via `finfo_open`, MIME type allowlist, SVG gated behind `allow_svg` option, file size limit
- **SVG sanitisation**: DOMDocument strips `<script>` nodes and `on*` attributes; regex fallback if DOMDocument absent
- **Raster path**: calls `convert_to_webp()` from `admin/includes/image_helpers.php` (loaded via `loadImageHelpers()`); falls back to `move_uploaded_file` with original extension on GD/WebP failure
- **Directory creation**: `mkdir(0777, true)` + `@chmod($dir, 0777)` — consistent with working categories upload pattern
- **Audit log**: every upload attempt (success or failure) written to `storage/logs/upload.log`
- **Return shape**: `['ok' => bool, 'relative_url' => string, 'absolute_path' => string, 'error' => string]`
- **Namespace**: `App\Services` — picked up by bootstrap.php `spl_autoload_register`

---

## Manual Regression Test Matrix

| # | Test | Expected | Method |
|---|------|----------|--------|
| 1 | Edit product → upload JPEG → save | `updated=1`, image visible in product card | Admin UI |
| 2 | Edit product → upload PNG → save | Same as above, or `.webp` if GD supports it | Admin UI |
| 3 | Edit product → no image change → save | `updated=1`, existing image unchanged | Admin UI |
| 4 | Edit product → category dropdown shows correct category | No `updated=0` from FK violation | Admin UI |
| 5 | Add new product with image → save | Product appears in list with image | Admin UI |
| 6 | Add new product without image | Product saved, `featured_image = NULL` in DB | Admin UI |
| 7 | Logo upload (Business Settings) → JPG/PNG | Logo saved at `/public/uploads/branding/…`, setting_value updated in DB | Admin UI |
| 8 | Logo upload → SVG | Same as above, SVG sanitised | Admin UI |
| 9 | Logo upload → PHP file disguised as JPG | Rejected with 500 error, no file written | API/UI |
| 10 | Upload file > 2 MB for branding | Rejected with size error | API/UI |
| 11 | Upload file > 10 MB for product image | Rejected with size error | API/UI |
| 12 | `storage/logs/upload.log` exists and grows | Log line written per upload attempt | File check |
| 13 | Categories upload flow (add/edit category image) | Unchanged, still works | Admin UI |

---

## Unchanged

- `admin/categories.php` — working reference, not touched
- `admin/edit-product.php` — if it exists separately, audit separately
- Database schema — no migrations required (branding dir was always empty, URL prefix `/public/uploads/branding/` is new)
- `banners.php` upload — working, not touched
