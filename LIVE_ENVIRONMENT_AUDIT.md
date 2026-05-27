# LIVE_ENVIRONMENT_AUDIT

Date: 2026-05-25
System Role: Local workspace is source of truth; production targeted for clean mirror.

## Phase 1 - Environment Verification

### 1.1 Config Presence (.env.production)
Required keys present:
- APP_URL
- DB_HOST
- DB_PORT
- DB_DATABASE
- DB_USERNAME
- DB_PASSWORD
- FTP_HOST
- FTP_PORT
- FTP_USER
- FTP_PASS

Result: PASS (keys present)

### 1.2 DNS + TCP Connectivity
- DB host from .env.production (`DB_HOST`) DNS: FAIL
- DB host from request (`mysql.gb.stackcp.com:41209`) DNS: OK
- DB host from request TCP: OK
- FTP host DNS: OK
- FTP host TCP: OK

Result: PARTIAL PASS

Important finding:
- `.env.production` DB host currently resolves to a non-resolvable hostname in this environment.
- StackCP host provided in deployment brief is reachable and used for production DB checks.

### 1.3 Production DB Connectivity (using StackCP host)
Connection target:
- Host: mysql.gb.stackcp.com
- Port: 41209
- User: from `.env.production`
- Database: from `.env.production`

Observed:
- DB connection: OK
- DB version: 10.6.25-MariaDB-log
- Database charset: latin1
- Database collation: latin1_swedish_ci
- SQL mode: NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION
- Table count: 21
- Engines: InnoDB=21

Result: PASS (reachable and queryable)

### 1.4 FTP Verification
Verified using passive FTP mode:
- FTP login: OK
- Passive mode: OK
- Root listing: OK
- Root write/delete test: PASS
- Writable path tests:
  - /storage: upload+delete PASS
  - /uploads: upload+delete PASS
  - /public/uploads: upload+delete PASS

Result: PASS

### 1.5 Live Runtime Smoke
HTTP checks against APP_URL:
- `/` => 200
- `/admin/login.php` => 200
- `/ping.php` => 500

Result: PARTIAL PASS

Important finding:
- `ping.php` is currently failing on live runtime pre-deploy.

## Phase 2 - Live Server Root Detection

Probe strategy:
- Uploaded temporary probe files to candidate paths and checked browser reachability.

Candidates tested:
- `/` => upload OK, HTTP probe OK
- `/public_html` => denied
- `/htdocs` => denied
- `/app` => upload OK, HTTP probe for app marker returned 404 page (not served root)
- `/www` => denied

Conclusion:
- Active web serving root is `/`.
- `/app` exists as an FTP directory but is not the web document root.

Cleanup:
- All temporary probe files removed from FTP.

## Gate Decision Before Full Mirror Deploy

Current gate status: CONDITIONAL GO
- GO for FTP mirror deployment path detection and filesystem sync to `/`.
- GO for DB migration using reachable StackCP DB host.
- Action required post-deploy: remediate `ping.php` runtime failure if still present after mirror.

## Audit Summary
- FTP connectivity and permissions: VERIFIED
- Live document root: VERIFIED as `/`
- Production DB connectivity: VERIFIED (via StackCP host)
- Runtime baseline issue found: `/ping.php` returns 500
