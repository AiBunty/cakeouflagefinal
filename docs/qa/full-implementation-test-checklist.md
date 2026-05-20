# Cakeouflage Full Implementation Test Checklist

Date: 2026-04-02
Scope: User flow, admin flow, reports, settings, communications, events, and demo data readiness

## 1. Public User Flow

- [ ] Homepage sections render: hero, featured categories, best sellers, custom cake CTA, course CTA, B2B CTA
- [ ] Desktop mega menu shows category and subcategory hierarchy from DB
- [ ] Mobile menu expands and links to category pages and Events
- [ ] Category listing supports sort, filter, pagination, and breadcrumb
- [ ] Product page supports variant-based pricing updates and add-to-cart
- [ ] Product page shows notes: SKU, flavour, packaging, lead time, delivery/pickup eligibility
- [ ] Register validates required fields and blocks duplicate email
- [ ] Login/logout works and session persists across page loads
- [ ] Forgot/reset password flow produces working reset token path
- [ ] Account profile update persists full name, phone, DOB
- [ ] Address CRUD works from account page
- [ ] Wishlist add/remove and wishlist to cart works
- [ ] Cart quantity updates and remove operations keep totals correct
- [ ] Checkout supports delivery and pickup mode switching
- [ ] Checkout creates order and invoice records
- [ ] Orders page shows status timeline and details
- [ ] Event listing page renders webinar and event entries
- [ ] Event detail page registration form submits and records registration

## 2. Admin Flow

- [ ] Admin login/logout and session guard on protected routes
- [ ] Dashboard cards load without errors
- [ ] Category CRUD updates frontend menu
- [ ] Product CRUD with variants updates frontend listing and PDP
- [ ] Course CRUD reflects on /course and /course/:slug
- [ ] Events CRUD reflects on /events and /events/:slug
- [ ] Orders page supports status updates and detail drawer
- [ ] Invoices page supports status and payment recording updates
- [ ] B2B accounts/quotes/orders pages support updates
- [ ] Communications page saves SMTP and WhatsApp settings and test actions
- [ ] WhatsApp template, mapping, and logs pages load and actions complete
- [ ] Reports page KPI summary returns values

## 3. Data and Demo Readiness

- [ ] 200+ products available in catalog
- [ ] 15+ retail users seeded
- [ ] 5+ B2B users/accounts seeded
- [ ] Demo orders across statuses: pending, confirmed, out_for_delivery, ready_for_pickup, cancelled
- [ ] Demo invoices across statuses: verification, paid, part_paid, overdue, rejected
- [ ] 4 courses and 4 batches available
- [ ] 6 events/webinars available with event detail pages
- [ ] Communication templates and log samples available

## 4. Security and Reliability

- [ ] CSRF-protected state mutations pass from UI forms
- [ ] Unauthorized API calls return 401 for account/admin paths
- [ ] No hardcoded secrets in deployment scripts
- [ ] Public debug endpoints removed from production
- [ ] Queue processing endpoint executes and updates job states

## 5. Deployment Readiness

- [ ] Seed script runs clean on hosted MySQL
- [ ] API health returns 200
- [ ] Categories and products APIs return 200
- [ ] Key frontend routes return 200
- [ ] Error log has no fatal parse/runtime errors after deploy
