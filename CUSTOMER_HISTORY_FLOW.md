# Customer History Flow

## End-to-End Flow
1. Admin opens `admin/crm_report.php?sub_report=users`.
2. Server renders customer intelligence rows from aggregated helper queries.
3. Admin clicks **View History** for a customer row.
4. Frontend calls `admin/ajax/customer-order-history.php` with:
   - `user_id`
   - `page`
   - `per_page`
5. Endpoint validates auth and permission (`crm_report`) and returns JSON payload.
6. Frontend renders:
   - customer insight strip
   - order timeline cards
   - payment/refund/comms mini panels
   - tags
   - follow-up and note actions

## Interactive Actions
- **Toggle Tag**
  - POST to `admin/ajax/customer-order-history.php` with action `toggle_tag`
  - Persists in `crm_customer_tags`
- **Add Internal Note**
  - POST action `add_note`
  - Persists as internal communication log (`communication_logs`)
- **Schedule Follow-up**
  - POST action `schedule_follow_up`
  - Persists in `reminders` as `follow_up`

## Timeline Pagination
- Timeline details are page-based and loaded on demand.
- Frontend keeps track of per-customer panel page state.

## Order Navigation
- Each timeline card includes direct order link:
  - `admin/orders.php?id=ORDER_ID`

## Security Controls
- Endpoint requires:
  - admin authentication
  - explicit `crm_report` permission
  - CSRF validation on POST actions
