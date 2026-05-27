# Admin Modules DB Compatibility Pass/Fail (MariaDB 10.6)

Date: 2026-05-26
Environment: local Docker (`mariadb:10.6`) aligned with remote StackCP MariaDB target

## Fixed blockers in this cycle

1. Import/export XLSX compatibility
- Root cause: installed PhpSpreadsheet version does not provide `setCellValueByColumnAndRow()`.
- Fix: replaced with coordinate-based `setCellValue()` in `ExcelService`.

2. Product export SQL compatibility
- Root cause: MariaDB SQL parsing failure from string-literal handling in grouped variant concat expression.
- Fix: normalized SQL literal usage in `productsExportCsv()`.

3. Refund report schema mismatch
- Root cause: query selected optional columns not present in this schema (`refund_transactions.settlement_reference`) and incompatible admin name column assumption.
- Fix: schema-aware select for `settlement_reference` and use `admins.full_name` for processor display.

## Validation evidence

1. Broad admin pages scan
- Source: `storage/logs/qa_admin_pages_scan.json`
- Result: 71 total pages, 0 non-200 responses.

2. Previously failing endpoints re-check
- Source: `storage/logs/qa_import_export_refund_report_checks.json`
- Result:
  - `GET /api/admin/import/template` -> 200 (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)
  - `GET /api/admin/import/products/export` -> 200 (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)
  - `GET /api/admin/orders/export` -> 200 (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)
  - `GET /api/admin/refunds/report` -> 200 (`application/json`)

3. Media upload flow
- Probe: authenticated multipart upload to `POST /api/admin/media/upload` with `file` field.
- Result: 201 with JSON success payload (`Media uploaded`).

4. Runtime errors after final focused checks
- Source: `storage/logs/php-error.log`
- Result: no new entries emitted during the final validation window.

## Requested module pass/fail matrix (current)

- Data Import/Export: PASS
- Business Settings: PASS
- Communication: PASS
- Sub User Settings: PASS
- Media Center: PASS
- Products Media Upload: PASS
- Coupons: PASS
- Cake Toppers: PASS
- CRM Settings: PASS
- Follow Ups: PASS
- Sales Register: PASS
- Reports section (summary/finance/ageing/refund): PASS
- System Settings (SMTP/WhatsApp): PASS

## Schema compatibility notes

- `refund_transactions.settlement_reference` is absent in current schema and now handled by query fallback.
- `orders.amount_collected` exists and `orders.actual_received_amount` is absent; refund logic already handles this variation.
- `admin_action_logs.action_type` exists via applied backfill migration.

## Production migration gate (for these blockers)

Status: READY

The previously failing compatibility blockers (`import_template`, `products_export`, `orders_export`, `refund_report`) are now passing on MariaDB 10.6 in local parity validation.