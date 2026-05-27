# Release Readiness Gate

Generated at: 2026-05-25 16:20:53 +05:30
Branch: release/stabilization-v1

## Scope

Final production hardening pass focused on:
- Finance and reporting correctness.
- Refund policy and listing stability.
- Invoice and payment flow continuity.
- Regression checks for online, manual, and BYOC order lifecycles.

## Code Changes in This Pass

- Added `app/Services/FinancialReconciliationService.php`.
- Wired reconciliation output into:
  - `GET /api/admin/finance/summary`
  - `GET /api/admin/reports/summary`
- Hardened `GET /api/admin/refunds` filters:
  - `status`
  - `q` (refund no, order no, customer name, mobile)
  - `date_preset` (`today`, `weekly`, `monthly`, `yearly`, `custom`, `all`)
  - `from_date`, `to_date`
- Fixed admin name column usage in refund/history queries (`admins.full_name`).

## Validation Status

- PHP lint:
  - `app/Services/FinancialReconciliationService.php` -> pass
  - `app/Controllers/AdminApiController.php` -> pass
- Admin endpoint smoke -> pass for:
  - `/api/admin/dashboard/summary`
  - `/api/admin/finance/summary`
  - `/api/admin/reports/summary`
  - `/api/admin/orders?limit=5`
  - `/api/admin/refunds`
- QA flow status:
  - Online flow -> pass
  - Manual flow -> pass
  - BYOC flow -> pass
  - Refund flow -> request pass, requester self-approval blocked by dual-approval policy (expected)

## Release Gate Decision

Status: READY FOR DEPLOYMENT CANDIDATE

Conditions:
- Keep dual-approval policy active for refunds.
- Execute production deploy checklist (backup, env sanity, debug off, health checks) before cutover.

## Predeploy Hygiene Pass (Command Evidence)

Executed at: 2026-05-25 16:24:25 +05:30

### 1) Git and Environment Sanity

Command:
```powershell
git rev-parse --abbrev-ref HEAD
git rev-parse --short HEAD
Select-String -Path .env -Pattern "^(APP_ENV|APP_DEBUG|SESSION_COOKIE_SECURE|SESSION_COOKIE_SAMESITE|SESSION_COOKIE_LIFETIME)=" | ForEach-Object { $_.Line }
```

Output:
```text
release/stabilization-v1
03e71c7
APP_ENV=production
APP_DEBUG=true
SESSION_COOKIE_SECURE=0
SESSION_COOKIE_SAMESITE=Lax
SESSION_COOKIE_LIFETIME=7200
```

Action taken:
```text
.env updated: APP_DEBUG=true -> APP_DEBUG=false
```

Validation command:
```powershell
Select-String -Path .env -Pattern "^APP_ENV=|^APP_DEBUG=" | ForEach-Object { $_.Line }
php -r "require 'app/bootstrap.php'; echo 'APP_DEBUG='.(defined('APP_DEBUG')?APP_DEBUG:'undefined').PHP_EOL; echo 'display_errors='.ini_get('display_errors').PHP_EOL;"
```

Validation output:
```text
APP_ENV=production
APP_DEBUG=false
APP_DEBUG=0
display_errors=0
```

### 2) Session and Log Directory Hygiene

Command:
```powershell
"storage/sessions","storage/logs" | ForEach-Object {
  $p=$_
  "Path=$p Exists=$(Test-Path $p)"
  $f=Join-Path $p ("predeploy_probe_"+[guid]::NewGuid().ToString('N')+".tmp")
  "ok" | Out-File -FilePath $f -Encoding ascii
  "WriteProbe=$f Result=ok"
  Remove-Item $f -Force
}
```

Output:
```text
Path=storage/sessions Exists=True
WriteProbe=storage\sessions\predeploy_probe_82f387beb7af4776838c9d91724009f1.tmp Result=ok
Path=storage/logs Exists=True
WriteProbe=storage\logs\predeploy_probe_8c159d13d74f4ed4a1be8d81a68e44bb.tmp Result=ok
```

### 3) Health Checks

Command:
```powershell
Invoke-WebRequest -Uri "http://localhost:8080/api/health" -UseBasicParsing
Invoke-WebRequest -Uri "http://localhost:8080/api/health/db" -UseBasicParsing
```

Output:
```text
Unhandled exception: call_user_func_array(): Argument #1 ($callback) must be a valid callback, class App\Controllers\ApiController does not have a method "health"
/var/www/html/app/Core/App.php:341
```

Fallback liveness command:
```powershell
$r=Invoke-WebRequest -Uri "http://localhost:8080/category" -UseBasicParsing
"StatusCode=$($r.StatusCode)"
"ContentLength=$($r.RawContentLength)"
```

Fallback output:
```text
StatusCode=200
ContentLength=115542
```

Operational readiness fallback:
```powershell
pwsh -ExecutionPolicy Bypass -File scripts/qa/run_admin_endpoint_smoke.ps1
```

Output (summary):
```text
/api/admin/dashboard/summary -> 200
/api/admin/finance/summary -> 200
/api/admin/reports/summary -> 200
/api/admin/orders?limit=5 -> 200
/api/admin/refunds -> 200
```

### 4) Log Hygiene Snapshot

Command:
```powershell
Get-Content storage/logs/php-error.log -Tail 20
```

Observed:
```text
Recent entries include historical admin/dashboard header warnings and the /api/health callback error above.
No new fatal errors observed in finance/report/refund endpoints during admin smoke run.
```

### 5) Backup Checklist Execution

Command:
```powershell
$backupDir="storage/backups"
if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }
$backupFile=Join-Path $backupDir ("predeploy_"+(Get-Date -Format "yyyyMMdd_HHmmss")+".sql")
docker exec cakeouflage-db sh -lc "mysqldump -uroot -proot cakeouflage_local" > $backupFile
"BackupFile=$backupFile"
"SizeBytes=$((Get-Item $backupFile).Length)"
```

Output:
```text
BackupFile=storage\backups\predeploy_20260525_162530.sql
SizeBytes=1019323
```

Backup verification command:
```powershell
Get-ChildItem storage/backups -File | Sort-Object LastWriteTime -Descending | Select-Object -First 3 Name,Length,LastWriteTime
```

Verification output:
```text
predeploy_20260525_162530.sql  1019323  25-05-2026 16:25:32
predeploy_20260525_162404.sql  1019449  25-05-2026 16:24:07
```

### Predeploy Verdict

- PASS: debug-off hygiene, session/log writable checks, backup generation, admin operational smoke.
- BLOCKER: `/api/health` and `/api/health/db` routes are miswired (`ApiController::health` callback missing) and should be fixed before production cutover.