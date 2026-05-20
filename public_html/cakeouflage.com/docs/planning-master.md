# Cakeouflage Planning Master

Last updated: 2026-04-01
Owner: Product + Engineering
Deployment target: Serverbyt StackCP shared hosting (PHP 8.1 + MySQL)

## 1) Canonical Scope Lock

This project is locked as a full bakery commerce and operations platform, not a static website.

Included business domains:
- Retail e-commerce (catalog, cart, checkout, account)
- Custom cake lead and approval flow
- Delivery and pickup fulfilment (Nashik radius rules)
- Course pages, batches, and enquiries
- B2B onboarding, quotes, and bulk ordering
- Finance and invoice verification flows
- Email and WhatsApp communications with queue/cron
- Meta WhatsApp template sync and approval workflow
- Full admin operations and reporting

## 2) Implementation Reality (Repo-Aligned)

Current repository already includes broad coverage for:
- Public pages and admin pages
- API for catalog, auth, cart, checkout, fulfilment, B2B, and communications
- Queue and cron endpoints for asynchronous communication operations
- Shared hosting deployment documentation and environment-based configuration

Current architecture and quality risks to control:
- Large controller concentration increases regression risk
- Mixed controller SQL and service abstraction increases maintenance overhead
- High feature breadth requires strict validation gates before launch

## 3) Non-Negotiable Technical Rules

1. Keep environment secrets only in `.env`; never hardcode credentials.
2. Keep CSRF protection on all state-changing API endpoints.
3. Keep auth and role checks mandatory for admin and finance actions.
4. Use queue + cron for email and WhatsApp sends; avoid direct send in order/payment controllers.
5. Keep retail and B2B workflow logic separated while sharing core entities safely.
6. Preserve shared layout partials and design token consistency across views.
7. Ensure DB schema and query conditions remain consistent after each change.

## 4) Delivery Tracks

Track A (Stability and Correctness):
- Data integrity, checkout correctness, invoice/payment correctness, queue reliability, security controls

Track B (Feature Completeness and Experience):
- Category depth, homepage and collections polish, B2B UX flow, courses module, reports and admin usability

## 5) Execution Phases (Mapped to Your Full Plan)

### Phase 1: Foundation and Guardrails
- Routing, env loading, DB health checks, shared partial structure, baseline security

### Phase 2: Catalog and Frontend Depth
- Multi-level categories, listing filters/sort, product detail variant pricing, seeded demo depth

### Phase 3: Auth, Cart, Checkout, Fulfilment
- Auth lifecycle, cart correctness, checkout flow, delivery/pickup slot logic, order and invoice generation

### Phase 4: Admin Core
- Category/product CRUD, images, content/banners, admin dashboard primitives

### Phase 5: Finance and B2B
- Payment verification lifecycle, receivables reporting, B2B registration/approval/quote/order conversion

### Phase 6: Course and CRM
- Courses and batches, customer profile enrichment, reminders and lifecycle triggers

### Phase 7: Communication Platform
- Email templates, WhatsApp templates, mappings, queue and logs

### Phase 8: Meta Integration Reliability
- Meta settings, template sync, approval submission, status synchronization

### Phase 9: Launch Hardening and Deployment
- UAT, security pass, responsive pass, cron validation, StackCP deployment and rollback checklist

## 6) Priority Model (Use in Every Sprint)

- P0: Data integrity, auth/security, order/invoice/payment correctness, queue stuck/failure incidents
- P1: Admin reliability, fulfilment edge cases, B2B flow correctness, reporting correctness
- P2: UI polish, optional refactors, secondary automation improvements

## 7) Weekly Working Cadence

Daily routine:
- 15 min: backlog re-rank and blocker check
- 90-120 min: single deep-work execution block on top P0/P1 item
- 30 min: validation and notes update

Twice weekly:
- Regression review across checkout, finance, comms, and admin mutations
- Scope correction and re-prioritization

## 8) Definition of Done (Strict)

Task is complete only when:
- Code implemented and reviewed against schema and business rules
- Manual validation steps executed and noted
- No security regressions in auth, CSRF, role checks, or upload validation
- Queue/cron impact considered where communication events are involved
- Rollback note added for risky changes

## 9) Live Backlog Template

| ID | Priority | Domain | Outcome | Dependency | Test Evidence | Owner | Status |
|---|---|---|---|---|---|---|---|
| BL-001 | P0 | Checkout | Prevent duplicate order placement | Auth + cart session | API + UI smoke | TBD | Todo |

## 10) Replan Rules

Trigger immediate replan if:
- Any P0 bug appears in auth/checkout/order/invoice/payment flows
- Queue failure or stuck jobs exceed one business cycle
- Deployment blocker appears on StackCP infra constraints

Replan protocol:
1. Freeze non-critical feature work for 24h
2. Re-rank all tasks by P0/P1/P2 and business impact
3. Publish updated week scope with explicit ownership and test gates

## 11) Open Decisions Required From You

1. Launch date and non-movable deadline
2. Solo build or team ownership split
3. Launch must-have modules vs post-launch modules
4. Day-1 expected order volume and traffic estimate
5. Payment scope for launch (manual only vs payment gateway)
6. First release focus priority (B2C-first vs B2B-first)
7. Day-1 mandatory KPIs and reports
8. Production rollback method and backup frequency

## 12) Next Execution Step

After you answer the open decisions, freeze sprint scope for the next 2 weeks and convert the top 12 items into execution tickets with owners, estimates, and test evidence requirements.

## 13) Implementation Audit (Code-Verified)

Audit date: 2026-04-01

### Implemented and wired end-to-end

- Core router with public, API, admin API, and cron endpoints
- CSRF guard for state-changing API calls
- Retail catalog API (categories, products, product detail) and wired shop/product pages
- Customer auth API (register/login/forgot/reset/me/logout) and wired auth pages
- Cart API (fetch/add/update/remove/coupon) and wired cart interactions
- Checkout preview and place-order APIs wired to checkout page
- Admin auth/dashboard and major admin modules wired through admin JS
- Admin product/category CRUD, media, import, orders, invoices, finance summary/ageing
- Communication stack: SMTP settings/test, WhatsApp settings/test, template flows, logs, retries
- Meta WhatsApp sync/approval/preview/test-send flows and cron sync endpoint
- CRM/automation endpoints (rules, reminders, birthdays, queue process)
- B2B admin operations (accounts, quotes, conversion, orders)
- Schema includes broad domain tables for retail, B2B, finance, CRM, communication, and automation

### Partially implemented (UI or data model exists, flow incomplete)

- Public course page exists, but it is currently static demo content instead of DB-backed catalog/batches
- B2B landing page exists, but dedicated B2B auth/dashboard/order-builder pages are not routed to concrete pages yet
- Customer account/order history/wishlist pages exist, but API coverage is limited (no customer order history API or wishlist API)
- Inquiry-oriented modules (contact/custom cake/course enquiry) have page/form presence but no unified inquiry API workflow

### Not implemented yet (or explicitly placeholder-routed)

- Placeholder-routed pages under B2B paths:
	- /b2b/corporate-orders
	- /b2b/bulk-orders
	- /b2b/corporate-gifting
	- /b2b/reseller
	- /b2b/login
	- /b2b/register
	- /b2b/dashboard
	- /b2b/quote-request
	- /b2b/order-builder
- Custom cake inquiry page route currently mapped to placeholder handler
- Admin courses page route currently mapped to placeholder handler
- Admin coupons page route currently mapped to placeholder handler
- Course detail route currently renders placeholder

### Immediate correctness issue discovered

- B2B hero CTA links use /b2b-register and /b2b-login while router expects /b2b/register and /b2b/login. This creates broken navigation from the B2B page.

## 14) Pending Work Plan (Execution Order)

Use this order to minimize regressions and unblock launch-critical flows.

### Wave 1 (P0): Route and journey integrity

1. Fix B2B CTA route mismatch on public B2B page.
2. Replace placeholder for custom cake inquiry with real page + submit flow.
3. Replace placeholder for B2B login/register/dashboard with real pages and session flow.
4. Add non-admin B2B APIs for registration, login/logout, profile, quote request, and dashboard summary.

Exit criteria:
- Public B2B visitor can apply, login, and access dashboard without placeholder routes.

### Wave 2 (P0/P1): Customer self-service completeness

1. Add customer API for order history and order detail timeline.
2. Add wishlist APIs (list/add/remove) and wire wishlist page.
3. Add account profile/address update APIs and wire account page actions.

Exit criteria:
- Logged-in user can view past orders, manage wishlist, and maintain profile/address data.

Pre-Wave-3 compact manual QA checklist:
1. Account page loads for logged-in users and redirects guests to login.
2. Profile save updates full name/phone and survives refresh.
3. Address CRUD works: add, edit, delete, set default.
4. Orders page lists past orders and opens order detail timeline.
5. Wishlist page lists saved products and remove action works.
6. Product detail "save to wishlist" works for logged-in users.
7. Wishlist "add to cart" reflects in cart counter.
8. API permission checks return 401 for guest calls to account/order/wishlist endpoints.
9. No JS console errors on /account, /orders, /wishlist.
10. CSRF-protected mutations still succeed from UI forms/buttons.

### Wave 3 (P1): Courses and inquiries

1. Replace static course content with DB-driven course and batch rendering.
2. Build course detail page from slug.
3. Add inquiry APIs for contact, course enquiries, and custom cake requests (reusing inquiries table).
4. Add admin course CRUD and wire /admin/courses page.

Exit criteria:
- Course and enquiry journeys persist to DB and are manageable from admin.

### Wave 4 (P1): Coupons and checkout strengthening

1. Implement admin coupons management page and APIs.
2. Complete coupon validation lifecycle (dates, usage limits, minimum cart amount) with clear user messages.
3. Add checkout validation for edge cases (slot unavailable, invalid pincode, stale cart prices).

Exit criteria:
- Coupon and checkout behavior is deterministic under normal and edge conditions.

### Wave 5 (P1/P2): Hardening and launch readiness

1. Add smoke test checklist and runbook for retail order, B2B quote-to-order, invoice payment verification, and queue dispatch.
2. Add cron verification runbook for queue processing and template sync.
3. Add backup and rollback checklist for StackCP deployment.

Exit criteria:
- Release candidate can be verified using repeatable checklist before every deployment.

## 15) Suggested 2-Week Sprint Split

Week 1:
- Wave 1 complete
- Start Wave 2 (orders + wishlist)

Week 2:
- Finish Wave 2
- Start Wave 3 (course detail + inquiries + admin courses skeleton)

## 16) QA Execution Update (2026-04-02)

Completed in codebase:
- Added events/webinars module end to end (public pages, public APIs, admin page, admin APIs)
- Added event registration inquiry flow and storage tables
- Expanded demo seed data for users, B2B accounts, events, registrations, orders, invoices, payments, communications
- Added QA deliverables in docs/qa (full checklist, bug summary, E2E scenarios, smoke script, demo credential notes)
- Removed hardcoded FTP credentials from deployment script and switched to environment variables

Pending execution step:
- Run schema + seed on target database and execute authenticated smoke pass to mark checklist items as passed/fail with evidence

Week 2 review gate:
- No placeholder routes remain on launch-critical public/B2B/customer journeys.
