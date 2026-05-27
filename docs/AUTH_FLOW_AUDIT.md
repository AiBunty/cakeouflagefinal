# AUTH FLOW AUDIT

Date: 2026-05-26
Scope: Customer OTP login, session persistence, dashboard access gate, and auth-dependent API loading.

## Root Cause Summary
- Symptom reported: user appears logged out after OTP login and account data does not load.
- Primary backend blocker: strict SQL mode failure in orders API (`ONLY_FULL_GROUP_BY`) caused `/api/orders` failure.
- UX side effect: frontend treated partial module failure as account-load failure, which looked like auth failure.

## Backend Remediations
- Updated OTP verification flow in `ApiController::verifyOtp()`:
  - Parses and respects remember-device flag.
  - Regenerates session id after successful OTP verification.
  - Refreshes session cookie with explicit options.
  - Returns redirect target consistently.
- Updated orders list query in `ApiController::ordersList()`:
  - Replaced non-compliant grouping with GROUP BY-safe aggregated subqueries.
  - Preserves order summary, item count, and coupon summary payload shape.

## Frontend Remediations
- Login flow now sends `remember_device` during OTP verification.
- After OTP verify, frontend performs `/api/auth/me` check before redirecting.
- Dashboard bootstrap now:
  - Validates auth first.
  - Uses `Promise.allSettled` for profile/orders/wishlist/addresses.
  - Avoids global auth-failed fallback when one module fails.

## Observed Risk Reductions
- Session fixation risk reduced by session id regeneration on login.
- False logout perception reduced by resilient partial-data loading.
- SQL strict-mode regressions reduced for orders aggregate query.

## Remaining Checks Before Production Sign-off
- Run live OTP login test on production URL.
- Confirm cookie attributes (Secure/SameSite/Lifetime) under real TLS.
- Confirm `/api/auth/me` and `/api/orders` success in production logs.
