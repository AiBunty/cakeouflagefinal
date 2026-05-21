$ErrorActionPreference='Stop'

function Get-CsrfAndSession {
  param([string]$BaseUrl)
  $sess = $null
  $page = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/login" -SessionVariable sess
  $m = [regex]::Match($page.Content, '<meta\s+name="csrf-token"\s+content="([^"]+)"')
  if (-not $m.Success) { throw 'CSRF token not found on /login' }
  [pscustomobject]@{ Token = $m.Groups[1].Value; Session = $sess }
}

function Send-Otp {
  param([string]$BaseUrl, [string]$Email, [string]$Name, [string]$Csrf, $Session)
  $body = @{ email=$Email; name=$Name } | ConvertTo-Json
  try {
    $r = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/send-otp" -Method Post -ContentType 'application/json' -Body $body -WebSession $Session -Headers @{ 'X-CSRF-Token' = $Csrf }
    return [pscustomobject]@{ ok=$true; code=[int]$r.StatusCode; body=$r.Content }
  } catch {
    $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode.value__ } else { -1 }
    $respBody = ''
    try { if ($_.Exception.Response) { $respBody = (New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() } } catch {}
    return [pscustomobject]@{ ok=$false; code=$code; body=$respBody }
  }
}

function Verify-Otp {
  param([string]$BaseUrl, [string]$Email, [string]$Otp, [string]$Csrf, $Session)
  $body = @{ email=$Email; otp=$Otp; name='Local Test User'; phone='+919999999999' } | ConvertTo-Json
  try {
    $r = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/api/verify-otp" -Method Post -ContentType 'application/json' -Body $body -WebSession $Session -Headers @{ 'X-CSRF-Token' = $Csrf }
    return [pscustomobject]@{ ok=$true; code=[int]$r.StatusCode; body=$r.Content }
  } catch {
    $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode.value__ } else { -1 }
    $respBody = ''
    try { if ($_.Exception.Response) { $respBody = (New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() } } catch {}
    return [pscustomobject]@{ ok=$false; code=$code; body=$respBody }
  }
}

function Get-LatestOtp {
  param([string]$Email)
  $cmd = "SELECT otp FROM otp_verifications WHERE email='$Email' ORDER BY id DESC LIMIT 1;"
  $otp = docker compose exec -T db mariadb -N -B -uroot -proot -D cakeouflage_local -e $cmd
  return ($otp | Select-Object -First 1).Trim()
}

$baseUrl = 'http://localhost:8080'
$emailFallback = 'otp-fallback-local@example.com'
$emailDbActive = 'otp-dbactive-local@example.com'

$createSql = @"
CREATE TABLE IF NOT EXISTS smtp_settings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  host VARCHAR(190) NULL,
  port INT NULL,
  username VARCHAR(190) NULL,
  password_encrypted TEXT NULL,
  encryption ENUM('none','ssl','tls') NOT NULL DEFAULT 'tls',
  from_name VARCHAR(120) NULL,
  from_email VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
"@
docker compose exec -T db mariadb -uroot -proot -D cakeouflage_local -e $createSql | Out-Null

# Scenario A: fallback path (no active DB SMTP)
docker compose exec -T db mariadb -uroot -proot -D cakeouflage_local -e "UPDATE smtp_settings SET is_active=0;" | Out-Null
$ctxA = Get-CsrfAndSession -BaseUrl $baseUrl
$sendA = Send-Otp -BaseUrl $baseUrl -Email $emailFallback -Name 'Fallback Test' -Csrf $ctxA.Token -Session $ctxA.Session
$otpA = Get-LatestOtp -Email $emailFallback
$verifyA = if ($otpA) { Verify-Otp -BaseUrl $baseUrl -Email $emailFallback -Otp $otpA -Csrf $ctxA.Token -Session $ctxA.Session } else { [pscustomobject]@{ ok=$false; code=-1; body='OTP not stored' } }

# Scenario B: DB-active path (active SMTP row)
$envMap = @{}
Get-Content .env | ForEach-Object {
  if ($_ -match '^[A-Za-z_][A-Za-z0-9_]*=') {
    $k,$v = $_ -split '=',2
    $envMap[$k] = $v
  }
}
$dbHost = $envMap['SMTP_HOST_LIVE']
$dbPort = $envMap['SMTP_PORT_LIVE']
$dbUser = $envMap['SMTP_USER_LIVE']
$dbPass = $envMap['SMTP_PASS_LIVE']
$dbEnc  = $envMap['SMTP_SECURE_LIVE']
$dbFromEmail = $envMap['SMTP_FROM_EMAIL_LIVE']
$dbFromName = $envMap['SMTP_FROM_NAME_LIVE']
if ([string]::IsNullOrWhiteSpace($dbEnc)) { $dbEnc='ssl' }
if ($dbEnc -eq 'smtps') { $dbEnc='ssl' }
if (($dbEnc -ne 'ssl') -and ($dbEnc -ne 'tls') -and ($dbEnc -ne 'none')) { $dbEnc='ssl' }

$esc = {
  param([string]$s)
  if ($null -eq $s) { return '' }
  return $s.Replace('\\','\\\\').Replace("'","''")
}
$sqlB = "UPDATE smtp_settings SET is_active=0; INSERT INTO smtp_settings (host,port,username,password_encrypted,encryption,from_name,from_email,is_active) VALUES ('{0}',{1},'{2}','{3}','{4}','{5}','{6}',1);" -f (&$esc $dbHost),([int]$dbPort),(&$esc $dbUser),(&$esc $dbPass),(&$esc $dbEnc),(&$esc $dbFromName),(&$esc $dbFromEmail)
docker compose exec -T db mariadb -uroot -proot -D cakeouflage_local -e $sqlB | Out-Null

$ctxB = Get-CsrfAndSession -BaseUrl $baseUrl
$sendB = Send-Otp -BaseUrl $baseUrl -Email $emailDbActive -Name 'DB Active Test' -Csrf $ctxB.Token -Session $ctxB.Session
$otpB = Get-LatestOtp -Email $emailDbActive
$verifyB = if ($otpB) { Verify-Otp -BaseUrl $baseUrl -Email $emailDbActive -Otp $otpB -Csrf $ctxB.Token -Session $ctxB.Session } else { [pscustomobject]@{ ok=$false; code=-1; body='OTP not stored' } }

$logTail = docker compose exec -T web sh -lc "tail -n 160 /var/www/html/storage/logs/php-error.log 2>/dev/null || true"
$dbFallbackLogSeen = ($logTail -match 'OTP SMTP DB transport failed')

$result = [pscustomobject]@{
  fallback = [pscustomobject]@{ sendCode=$sendA.code; sendOk=$sendA.ok; otpStored=([bool]$otpA); verifyCode=$verifyA.code; verifyOk=$verifyA.ok }
  dbActive = [pscustomobject]@{ sendCode=$sendB.code; sendOk=$sendB.ok; otpStored=([bool]$otpB); verifyCode=$verifyB.code; verifyOk=$verifyB.ok }
  dbTransportFailureLogSeen = [bool]$dbFallbackLogSeen
}

$result | ConvertTo-Json -Depth 6
