# CRM UI Redesign

## Objective
Redesign the admin CRM report into a central customer intelligence workspace focused on order history and customer timeline operations.

## Delivered
- Replaced flat customer report with a compact intelligence grid in `admin/crm_report.php`.
- Added six summary header metrics for operational CRM visibility:
  - Total Customers
  - Repeat Buyers
  - Revenue Generated
  - Pending Follow-ups
  - Refund Customers
  - Active Today
- Added advanced filtering controls:
  - Universal search across customer attributes, order number, and item snapshots
  - Segment filters: all, repeat customers, refunded users, high spenders, inactive customers, pending payments, birthday/event buyers, recent buyers
  - Pagination and per-page controls
- Added expandable customer rows with lazy-loaded timeline details.
- Added quick action cluster per customer row:
  - View History (expand timeline)
  - WhatsApp
  - Call
  - Email

## New Assets
- `admin/assets/css/crm-report.css`
  - Professional compact style system with summary cards, KPI chips, timeline cards, and responsive behavior.
- `admin/assets/js/crm-report.js`
  - Client behavior for lazy expansion, pagination in expanded panel, tag toggles, note creation, and follow-up scheduling.

## Architectural Update
- New include facade:
  - `admin/includes/crm_helpers.php`
- Existing helper module was extended instead of replaced:
  - `admin/includes/crm_report_helpers.php`

## Notes
- Legacy tabs (overview, followups, jobs) remain available and functional to avoid abrupt operational disruption.
- Export (Excel/PDF) remains supported from the users tab.
