# Cakeouflage E2E Scenarios

Date: 2026-04-02

## Scenario 1: Retail Register to Pickup Order

1. Register a new user.
2. Login with same user.
3. Browse category and open a product.
4. Select variant and add to cart.
5. Checkout with pickup mode.
6. Place order.
7. Verify order appears in My Orders.
8. Verify order appears in Admin Orders.
9. Verify invoice created in Admin Invoices.

Expected result: Complete flow succeeds and order has pending/confirmed lifecycle.

## Scenario 2: Delivery Lifecycle Status Propagation

1. User places delivery order.
2. Admin marks invoice/payment verified.
3. Admin updates order to in_preparation.
4. Admin updates order to out_for_delivery.
5. User refreshes orders page and sees updated status.

Expected result: Status transitions visible in both admin and user order timelines.

## Scenario 3: Password Reset

1. User opens forgot password form.
2. Submit registered email.
3. Use generated token reset route.
4. Set new password.
5. Login with new password.

Expected result: Old password fails and new password succeeds.

## Scenario 4: Category and Product Admin Propagation

1. Admin creates category and subcategory.
2. Admin creates product with variant.
3. Open frontend menu and category page.
4. Verify product appears in listing and PDP works.

Expected result: Newly created taxonomy and product are visible on frontend.

## Scenario 5: Course and Event Publication

1. Admin creates or updates a course.
2. Admin creates an event/webinar.
3. Visit /course and /events pages.
4. Open detail pages.
5. Submit enquiry/registration forms.

Expected result: Public pages display published data and forms save records.

## Scenario 6: B2B Registration to Quote

1. B2B user registers.
2. Admin approves account in B2B Accounts.
3. B2B user logs in to dashboard.
4. B2B user submits quote request.
5. Admin sees quote in B2B Quotes.

Expected result: Quote request is visible and status is manageable by admin.

## Scenario 7: Communication Mapping and Logs

1. Admin updates communication templates.
2. Admin maps business events to templates.
3. Trigger a business event (order created).
4. Verify communication logs entries are created.

Expected result: Event mapping produces communication logs with sent/failed status.

## Scenario 8: Overdue Invoice Tracking

1. Create invoice with past due date and unpaid balance.
2. Verify invoice appears in overdue state.
3. Verify overdue count appears in finance/report summaries.
4. Verify reminder/queue item is generated if automation rule enabled.

Expected result: Overdue pipeline is visible in finance and automation monitoring.
