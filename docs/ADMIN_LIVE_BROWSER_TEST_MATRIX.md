# Admin Live Browser Test Matrix

## Scope
Live browser traversal executed against admin runtime at `http://localhost:8080/admin/*` after migration and compatibility fixes.

## Result Snapshot
- Total modules checked: 40
- PASS (HTTP 200, no unhandled exception): 36
- ACCESS_RESTRICTED (HTTP 403): 3
- SPECIAL_FLOW (download endpoint): 1

## Module Matrix
| Module | HTTP | Result | Notes |
|---|---:|---|---|
| dashboard.php | 200 | PASS | Loads and renders admin dashboard |
| orders.php | 200 | PASS | List view loads |
| order_details.php | 200 | PASS | Detail shell loads |
| refunds.php | 200 | PASS | Refund listing loads |
| refund_report.php | 200 | PASS | Fixed schema/runtime compatibility issues |
| products.php | 200 | PASS | Product list loads |
| categories.php | 200 | PASS | Category list loads |
| import-products.php | 200 | PASS | Import UI loads |
| import-version-history.php | 200 | PASS | Fixed DB handle variable mismatch |
| download_products.php | n/a | SPECIAL_FLOW | Browser treats as file download, not a navigated document |
| business-settings.php | 403 | ACCESS_RESTRICTED | Permission-gated for current logged-in admin |
| communications.php | 200 | PASS | Fixed MariaDB upsert compatibility |
| crm_settings.php | 200 | PASS | Fixed helper upsert compatibility |
| crm_report.php | 200 | PASS | Loads |
| crm_push_logs.php | 200 | PASS | Loads |
| crm_diagnostics.php | 200 | PASS | Fixed ONLY_FULL_GROUP_BY diagnostics query |
| crm_user_history.php | 200 | PASS | Loads |
| manual_order.php | 200 | PASS | Loads |
| build-your-own-cake.php | 200 | PASS | Loads |
| follow_ups.php | 200 | PASS | Fixed helper upsert + malformed markup artifact |
| toppers.php | 200 | PASS | Loads |
| banners.php | 200 | PASS | Loads |
| slots.php | 200 | PASS | Loads |
| production_plan.php | 200 | PASS | Loads |
| sales_register.php | 200 | PASS | Loads |
| sales_report.php | 200 | PASS | Redirects to register filters as expected |
| revenue_report.php | 200 | PASS | Redirects to register filters as expected |
| coupon_report.php | 200 | PASS | Loads |
| cash_report.php | 200 | PASS | Redirects to register filters as expected |
| bank_report.php | 200 | PASS | Redirects to register filters as expected |
| collection_report.php | 200 | PASS | Redirects to register filters as expected |
| credit_report.php | 200 | PASS | Redirects to register filters as expected |
| celebration_report.php | 200 | PASS | Loads |
| fulfillment_report.php | 200 | PASS | Fixed slot capacity column mismatch |
| coupons.php | 200 | PASS | Loads |
| kitchen_queue.php | 200 | PASS | Fixed ONLY_FULL_GROUP_BY aggregation |
| bank-alerts.php | 200 | PASS | Loads |
| admin_users.php | 403 | ACCESS_RESTRICTED | Permission-gated for current logged-in admin |
| manage-admins.php | 200 | PASS | Loads |
| change-password.php | 403 | ACCESS_RESTRICTED | Permission-gated for current logged-in admin |

## Notes
- 403 modules are permission restrictions for current session, not runtime fatals.
- TinyMCE CDN request failures observed in console are external network/plugin telemetry noise, not backend module failures.
