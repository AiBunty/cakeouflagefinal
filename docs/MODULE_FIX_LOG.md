# Module Fix Log

## Recovery Pass: Live Browser Failures

### 1) refund_report.php
- Symptom: HTTP 500 / generic admin failure.
- Root causes:
  - schema assumptions for optional refund/admin columns
  - brittle query handling on DB errors
- Fixes:
  - schema-aware column detection via `information_schema`
  - adaptive select expressions for optional columns
  - safe query execution with logged catches
- Outcome: HTTP 200 and table rows render.

### 2) import-version-history.php
- Symptom: unhandled exception (`Undefined variable $mysqli`).
- Fix: replaced `$mysqli` with active DB handle `$conn`.
- Outcome: HTTP 200.

### 3) communications.php
- Symptom: MariaDB SQL syntax error on template upsert.
- Fix: removed unsupported alias form and switched to `VALUES(...)` references in upsert CASE branches.
- Outcome: HTTP 200, no unhandled exception.

### 4) admin/includes/crm_settings_helpers.php
- Symptom A: MariaDB syntax error in settings default upsert.
- Fix A: replaced alias-based upsert with `VALUES(setting_value)` path.
- Symptom B: strict group-by failure in diagnostics query.
- Fix B: restructured query to join pre-aggregated 7-day stats subquery.
- Outcome: crm settings and diagnostics pages load (HTTP 200).

### 5) follow_ups.php
- Symptom: malformed markup artifact in Google review field block.
- Fix: corrected invalid injected tag sequence.
- Outcome: HTTP 200 and form renders correctly.

### 6) fulfillment_report.php
- Symptom: unknown column `order_slots.max_capacity`.
- Fix: switched to `COALESCE(order_slot_exceptions.override_capacity, order_slots.max_orders)`.
- Outcome: HTTP 200.

### 7) kitchen_queue.php
- Symptom: strict group-by error (`order_number isn't in GROUP BY`).
- Fix: aggregate non-grouped order fields with `MAX(...)` while grouping by `o.id`.
- Outcome: HTTP 200.

### 8) app/Core/QueueWorker.php
- Symptom: PDO syntax failure on prepared `SHOW COLUMNS ... LIKE :param`.
- Fix: replaced with `information_schema.COLUMNS` lookup for media_assets column existence.
- Outcome: queue worker can process media jobs without SQL prepare failure.

### 9) app/Services/VideoTranscodeService.php
- Symptom A: ffmpeg filter parse failure for `min(1920,iw)` expression.
- Symptom B: low observability on conversion failures.
- Symptom C: in-place MP4 canonical path edge case.
- Fixes:
  - changed filters to `scale=1920:-2:force_original_aspect_ratio=decrease` and `scale=1280:-2:...`
  - added ffmpeg stderr tail to thrown error message
  - bypassed in-place reconversion when source == target and file already exists.
- Outcome: valid recorder-generated webm job completed end-to-end.
