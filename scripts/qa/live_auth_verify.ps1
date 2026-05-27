$ErrorActionPreference = 'Stop'

$ts = Get-Date -Format 'yyyyMMdd_HHmmss'
$proofDir = "storage/logs/smoke_auth_http_$ts"
New-Item -ItemType Directory -Force -Path $proofDir | Out-Null

$cookie = Join-Path $proofDir 'cookies.txt'
$email = "qa.http.$ts@cakeouflage.com"

$html = curl.exe --silent --show-error --fail --location --cookie-jar "$cookie" "https://cakeouflage.com/account"
$html | Out-File (Join-Path $proofDir '00_account_page.html') -Encoding utf8

$m = [regex]::Match($html, 'window.__csrf\s*=\s*"([^"]+)"')
if (-not $m.Success) {
    throw 'CSRF token not found on /account'
}
$csrf = $m.Groups[1].Value

$sendPayloadPath = Join-Path $proofDir 'send_payload.json'
Set-Content -Path $sendPayloadPath -Value (@{ email = $email } | ConvertTo-Json -Compress) -Encoding utf8

$verifyPayloadPath = Join-Path $proofDir 'verify_payload.json'
$emptyPayloadPath = Join-Path $proofDir 'empty.json'
Set-Content -Path $emptyPayloadPath -Value '{}' -Encoding utf8

curl.exe --silent --show-error --cookie "$cookie" --cookie-jar "$cookie" -H "Content-Type: application/json" -H "X-CSRF-Token: $csrf" --data-binary "@$sendPayloadPath" -D (Join-Path $proofDir '01_send_headers.txt') "https://cakeouflage.com/api/send-otp" -o (Join-Path $proofDir '01_send_body.json')

$otp = (php scripts/qa/get_live_otp.php $email).Trim()
if ([string]::IsNullOrWhiteSpace($otp) -or $otp.Length -ne 6) {
    throw "OTP invalid: $otp"
}
$otp | Out-File (Join-Path $proofDir 'otp_value.txt') -Encoding ascii

Set-Content -Path $verifyPayloadPath -Value (@{ email = $email; otp = $otp; remember_device = '1' } | ConvertTo-Json -Compress) -Encoding utf8

curl.exe --silent --show-error --cookie "$cookie" --cookie-jar "$cookie" -H "Content-Type: application/json" -H "X-CSRF-Token: $csrf" --data-binary "@$verifyPayloadPath" -D (Join-Path $proofDir '02_verify_headers.txt') "https://cakeouflage.com/api/verify-otp" -o (Join-Path $proofDir '02_verify_body.json')

curl.exe --silent --show-error --cookie "$cookie" -D (Join-Path $proofDir '03_me_headers.txt') "https://cakeouflage.com/api/auth/me" -o (Join-Path $proofDir '03_me_body.json')

curl.exe --silent --show-error --cookie "$cookie" -D (Join-Path $proofDir '04_orders_headers.txt') "https://cakeouflage.com/api/orders" -o (Join-Path $proofDir '04_orders_body.json')

curl.exe --silent --show-error --cookie "$cookie" --cookie-jar "$cookie" -H "Content-Type: application/json" -H "X-CSRF-Token: $csrf" --data-binary "@$emptyPayloadPath" -D (Join-Path $proofDir '05_logout_headers.txt') "https://cakeouflage.com/api/auth/logout" -o (Join-Path $proofDir '05_logout_body.json')

curl.exe --silent --show-error --cookie "$cookie" -D (Join-Path $proofDir '06_me_after_headers.txt') "https://cakeouflage.com/api/auth/me" -o (Join-Path $proofDir '06_me_after_body.json')

$summary = [ordered]@{
    timestamp = (Get-Date).ToString('o')
    email = $email
    proof_dir = $proofDir
    csrf_prefix = $csrf.Substring(0, [Math]::Min($csrf.Length, 8))
}
$summary | ConvertTo-Json -Depth 4 | Out-File (Join-Path $proofDir 'summary.json') -Encoding utf8

Write-Output "AUTH_HTTP_FLOW_OK"
Write-Output "PROOF_DIR=$proofDir"
