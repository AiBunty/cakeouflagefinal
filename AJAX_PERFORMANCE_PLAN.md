# AJAX Performance Plan

## Performance Principles
- Keep initial page payload light by avoiding full history rendering server-side.
- Fetch timeline data only when a customer row is expanded.
- Keep timeline responses paginated (`per_page` capped to prevent oversized payloads).

## Implemented Tactics
- **Lazy expansion**
  - Timeline details requested only after explicit row expansion.
- **Bounded response size**
  - Endpoint uses page/per-page with max per-page cap.
- **Aggregated customer list query**
  - Customer rows use aggregate subquery to avoid N+1 list queries.
- **Scoped detail queries**
  - Expanded payload uses user-scoped recent records (orders, payments, refunds, comms, follow-ups).
- **No heavy joins in initial listing for timeline internals**
  - Timeline internals fetched in dedicated endpoint.

## Operational Recommendations
- Add/verify indexes if production data volume is high:
  - `orders(user_id, created_at)`
  - `orders(user_id, updated_at)`
  - `order_items(order_id)`
  - `reminders(user_id, reminder_type, reminder_on)`
  - `communication_logs(user_id, created_at)`
  - `crm_customer_tags(user_id, tag_key)`
- Observe response times by segment and search patterns.
- Monitor slow-query logs for wildcard-heavy universal search.

## Future Improvements
- Debounced search with asynchronous table refresh.
- Server-side caching for summary header metrics.
- Optional prefetch of first timeline page for visible rows above the fold.
