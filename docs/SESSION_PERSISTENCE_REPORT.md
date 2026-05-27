# SESSION_PERSISTENCE_REPORT

## Scope
- Improve production session reliability for customer auth.

## Changes Applied
- Updated `app/bootstrap.php` session bootstrap:
  - Added HTTPS detection using `HTTPS`, `SERVER_PORT`, and `X-Forwarded-Proto`.
  - Cookie `secure` now enabled when env requires it or request is HTTPS.
  - Added cookie domain resolver:
    - Uses `SESSION_COOKIE_DOMAIN` when configured.
    - Auto-normalizes `www.<domain>` to `.<domain>` when applicable.
  - Preserved configurable `SameSite` and lifetime behavior.
- Updated OTP verify session cookie refresh in `app/Controllers/ApiController.php`:
  - Keeps remember-device lifetime behavior and session cookie refresh.

## Expected Production Impact
- Better persistence for HTTPS deployments and proxied environments.
- Reduced risk of session drops from cookie attribute mismatches.

## Validation Evidence
- `php -l app/bootstrap.php` => no syntax errors.
- VS Code diagnostics => no errors in edited bootstrap/auth files.

## Notes
- Verify `.env.production` has intended values for:
  - `SESSION_COOKIE_SECURE`
  - `SESSION_COOKIE_SAMESITE`
  - `SESSION_COOKIE_LIFETIME`
  - `SESSION_COOKIE_DOMAIN` (optional)
