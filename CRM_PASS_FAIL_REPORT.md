# CRM Pass/Fail Report

## Scope
Validation of redesigned CRM users module and supporting backend/AJAX components.

## Build/Syntax Checks
- PASS: `admin/includes/crm_report_helpers.php` (`php -l`)
- PASS: `admin/ajax/customer-order-history.php` (`php -l`)
- PASS: `admin/crm_report.php` (`php -l`)
- PASS: `admin/includes/crm_helpers.php` (`php -l`)

## Feature Validation Status
- PASS: New customer intelligence summary header metrics rendered in users tab.
- PASS: Universal search and segment filters wired server-side.
- PASS: Expandable customer rows implemented.
- PASS: Lazy timeline endpoint created and wired.
- PASS: Timeline pagination controls implemented in frontend JS.
- PASS: Tag toggle action implemented (POST + persistence layer).
- PASS: Internal note action implemented (POST + communication log write).
- PASS: Follow-up scheduling action implemented (POST + reminders write).
- PASS: Direct order link from timeline to `orders.php?id=...`.
- PASS: Runtime lazy expansion now succeeds for real customer data after helper query fix.
- PASS: Runtime POST actions (`toggle_tag`, `add_note`, `schedule_follow_up`) now accept JSON `user_id` and return success.

## Runtime Defects Found During QA (and Fixed)
- FIXED: Expand history returned 500 because `fetch_crm_customer_timeline_payload()` joined `products.category_id` (column does not exist in current schema).
	- Resolution: switched to `COALESCE(p.child_category_id, p.subcategory_id, p.collection_category_id)` in [admin/includes/crm_report_helpers.php](admin/includes/crm_report_helpers.php).
- FIXED: POST actions returned 422 `user_id is required` because endpoint only read query/form data and ignored JSON request bodies.
	- Resolution: parse JSON body first and include `body['user_id']` fallback in [admin/ajax/customer-order-history.php](admin/ajax/customer-order-history.php).

## Runtime QA Status
- PASS: Browser-level interaction smoke test in admin UI against live data (login, filters, summary cards, row expand, timeline, order links).
- PASS: Endpoint-level action execution verified via authenticated browser fetch calls with CSRF token.
- PASS: New note writes are visible in timeline (`crm_note` appeared in Comms & Follow-ups panel).
- PARTIAL: Follow-up creation succeeded (HTTP 200) but newly created follow-up may not always appear in the compact combined panel when historical entries dominate the top slice.

## Remaining Manual Checks
- PENDING: Verify WhatsApp/call/email links in a real operator browser and device context.
- PENDING: Validate timeline rendering for edge users (no orders, many refunds, high volume history).
- PENDING: UX enhancement candidate: replace `window.prompt` action collection with inline/modal form controls for broader browser compatibility.

## Conclusion
Implementation, runtime QA, and critical defect remediation are complete. Module is functionally stable for further UAT, with remaining checks focused on UX polish and edge-case coverage.
