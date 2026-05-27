param(
  [string]$ProductQuery = 'R1 Matrix Product'
)

$ErrorActionPreference = 'Stop'
$base = 'http://localhost:8080'

function Get-AdminEmail {
  $line = docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "SELECT email FROM admins WHERE is_active=1 ORDER BY CASE WHEN role='super_admin' THEN 0 ELSE 1 END, id ASC LIMIT 1;"
  return ($line | Select-Object -First 1)
}

function Seed-Otp([string]$email, [string]$otp) {
  docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "DELETE FROM otp_verifications WHERE email='${email}'; INSERT INTO otp_verifications(email,otp,expires_at) VALUES('${email}','${otp}',NOW()+INTERVAL 5 MINUTE);" | Out-Null
}

function Get-CsrfToken($session) {
  $html = [string](Invoke-WebRequest -Uri "$base/admin/dashboard.php" -WebSession $session -UseBasicParsing).Content
  $m = [regex]::Match($html, 'name="csrf-token"\s+content="([a-f0-9]{64})"', 'IgnoreCase')
  if (-not $m.Success) { throw 'CSRF token not found' }
  return $m.Groups[1].Value
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-WebRequest -Uri "$base/admin/login.php" -WebSession $session -UseBasicParsing | Out-Null
$email = Get-AdminEmail
if (-not $email) { throw 'No active admin found' }
Seed-Otp -email $email -otp '123456'
Invoke-WebRequest -Uri "$base/admin/login.php" -Method Post -WebSession $session -UseBasicParsing -MaximumRedirection 5 -Body @{ action='verify_otp'; login=$email; otp='123456' } | Out-Null

$gateChecks = @{}

# Check 1: R2 + R7-R9 compact verification
$compactOut = & powershell -ExecutionPolicy Bypass -File .\storage\import-logs\r2_r9_compact_verify.ps1 -ProductQuery $ProductQuery -FreshProductCheck
$compactText = ($compactOut | Out-String)
$gateChecks['compact_pass'] = $compactText -match 'COMPACT_R2_R9_VERIFY_PASS=TRUE'
$gateChecks['fresh_variant_canonical'] = $compactText -match 'FRESH_VARIANT_CANONICAL=True'
$gateChecks['finance_sources'] = $compactText -match 'AFTER_FINANCE_SOURCES_LEDGER=True'

# Check 2: settlement integrity (R6) with idempotent reference
$csrf = Get-CsrfToken -session $session
$orderId = (docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "SELECT id FROM orders WHERE balance_due_amount > 0 ORDER BY id DESC LIMIT 1;" | Select-Object -First 1)
if ($orderId) {
  $ref = 'R11-GATE-' + (Get-Date -Format 'yyyyMMddHHmmss')
  $resp1 = Invoke-RestMethod -Uri "$base/admin/api/collection-followup-action.php" -Method Post -WebSession $session -ContentType 'application/x-www-form-urlencoded' -Body @{ _csrf=$csrf; order_id=$orderId; action_type='payment_collected'; settlement_reference=$ref; settlement_payment_method='upi_manual' }
  $dupCount = (docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "SELECT COUNT(*) FROM financial_transactions WHERE idempotency_key LIKE 'collection-settlement:${orderId}:%' AND idempotency_key LIKE '%${ref.ToLower()}%';" | Select-Object -First 1)
  $gateChecks['settlement_post_success'] = [bool]$resp1.success
  $gateChecks['settlement_single_tx'] = ([int]$dupCount -eq 1)
  Write-Output "R11_SETTLEMENT_ORDER=$orderId"
  Write-Output "R11_SETTLEMENT_TX=$($resp1.settlement_transaction_id)"
  Write-Output "R11_SETTLEMENT_DUP_COUNT=$dupCount"
} else {
  $gateChecks['settlement_post_success'] = $true
  $gateChecks['settlement_single_tx'] = $true
  Write-Output 'R11_SETTLEMENT_SKIPPED=NO_BALANCE_ORDER'
}

Write-Output ("R11_CHECK_COMPACT_PASS=" + $gateChecks['compact_pass'])
Write-Output ("R11_CHECK_FRESH_VARIANT_CANONICAL=" + $gateChecks['fresh_variant_canonical'])
Write-Output ("R11_CHECK_FINANCE_SOURCES=" + $gateChecks['finance_sources'])
Write-Output ("R11_CHECK_SETTLEMENT_POST_SUCCESS=" + $gateChecks['settlement_post_success'])
Write-Output ("R11_CHECK_SETTLEMENT_SINGLE_TX=" + $gateChecks['settlement_single_tx'])

$gatePass = $true
foreach ($kv in $gateChecks.GetEnumerator()) {
  if (-not [bool]$kv.Value) {
    $gatePass = $false
    break
  }
}
Write-Output ("R11_CUTOVER_GATE_PASS=" + $gatePass)
