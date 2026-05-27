param(
    [string]$BaseUrl = "http://localhost:8080",
    [string]$EmailPrefix = "qa.auth",
    [string]$LogPath = "storage/logs/qa_local_auth_probe.json"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-CsrfToken {
    param(
        [string]$Html
    )

    $metaMatch = [regex]::Match($Html, '<meta\s+name="csrf-token"\s+content="([^"]+)"')
    if ($metaMatch.Success) {
        return $metaMatch.Groups[1].Value
    }

    $jsMatch = [regex]::Match($Html, 'window\.__csrf\s*=\s*"([^"]+)"')
    if ($jsMatch.Success) {
        return $jsMatch.Groups[1].Value
    }

    throw "Unable to locate CSRF token in HTML response"
}

function Write-ResultJson {
    param(
        [hashtable]$Payload,
        [string]$Path
    )

    $directory = Split-Path -Parent $Path
    if ($directory -and -not (Test-Path $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $Payload | ConvertTo-Json -Depth 8 | Set-Content -Path $Path -Encoding UTF8
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$email = "$EmailPrefix.$timestamp@example.com"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$result = [ordered]@{
    started_at = (Get-Date).ToString("o")
    base_url = $BaseUrl
    email = $email
    steps = @()
    pass = $false
    error = $null
}

try {
    $accountResp = Invoke-WebRequest -Uri "$BaseUrl/account" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $csrf = Get-CsrfToken -Html $accountResp.Content
    $result.steps += [ordered]@{ step = "load_account"; status = [int]$accountResp.StatusCode; csrf_present = [bool]($csrf) }

    $sendBody = @{ email = $email } | ConvertTo-Json
    $sendHeaders = @{ "Content-Type" = "application/json"; "X-CSRF-Token" = $csrf }
    $sendResp = Invoke-WebRequest -Uri "$BaseUrl/api/send-otp" -Method POST -Body $sendBody -Headers $sendHeaders -WebSession $session -UseBasicParsing -TimeoutSec 30
    $sendJson = $sendResp.Content | ConvertFrom-Json
    $result.steps += [ordered]@{ step = "send_otp"; status = [int]$sendResp.StatusCode; success = [bool]$sendJson.success; message = [string]$sendJson.message }

    $otp = (docker exec cakeouflage-db mariadb -uroot -proot -D cakeouflage_local -N -B -e "SELECT otp FROM otp_verifications WHERE email = '$email' ORDER BY id DESC LIMIT 1;").Trim()
    if ([string]::IsNullOrWhiteSpace($otp)) {
        throw "OTP not found in local database for $email"
    }
    $result.steps += [ordered]@{ step = "fetch_otp_from_db"; found = $true; otp_length = $otp.Length }

    $verifyBody = @{ email = $email; otp = $otp; remember_device = "1" } | ConvertTo-Json
    $verifyHeaders = @{ "Content-Type" = "application/json"; "X-CSRF-Token" = $csrf }
    $verifyResp = Invoke-WebRequest -Uri "$BaseUrl/api/verify-otp" -Method POST -Body $verifyBody -Headers $verifyHeaders -WebSession $session -UseBasicParsing -TimeoutSec 30
    $verifyJson = $verifyResp.Content | ConvertFrom-Json
    $result.steps += [ordered]@{ step = "verify_otp"; status = [int]$verifyResp.StatusCode; success = [bool]$verifyJson.success; redirect_to = [string]($verifyJson.data.redirect_to) }

    $authResp1 = Invoke-WebRequest -Uri "$BaseUrl/api/auth/me" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $authJson1 = $authResp1.Content | ConvertFrom-Json
    $result.steps += [ordered]@{ step = "auth_me_after_verify"; status = [int]$authResp1.StatusCode; success = [bool]$authJson1.success; user_id = [int]($authJson1.data.user.id) }

    $authResp2 = Invoke-WebRequest -Uri "$BaseUrl/api/auth/me" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $authJson2 = $authResp2.Content | ConvertFrom-Json
    $result.steps += [ordered]@{ step = "auth_me_refresh_probe"; status = [int]$authResp2.StatusCode; success = [bool]$authJson2.success }

    $logoutBody = @{} | ConvertTo-Json
    $logoutHeaders = @{ "Content-Type" = "application/json"; "X-CSRF-Token" = $csrf }
    $logoutResp = Invoke-WebRequest -Uri "$BaseUrl/api/auth/logout" -Method POST -Body $logoutBody -Headers $logoutHeaders -WebSession $session -UseBasicParsing -TimeoutSec 30
    $logoutJson = $logoutResp.Content | ConvertFrom-Json
    $result.steps += [ordered]@{ step = "logout"; status = [int]$logoutResp.StatusCode; success = [bool]$logoutJson.success }

    try {
        $authResp3 = Invoke-WebRequest -Uri "$BaseUrl/api/auth/me" -WebSession $session -UseBasicParsing -TimeoutSec 30
        $result.steps += [ordered]@{ step = "auth_me_after_logout"; status = [int]$authResp3.StatusCode; expected_unauthorized = $false }
    } catch {
        $statusCode = 0
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        $result.steps += [ordered]@{ step = "auth_me_after_logout"; status = $statusCode; expected_unauthorized = ($statusCode -eq 401) }
    }

    $result.pass = $true
} catch {
    $result.error = $_.Exception.Message
    $result.pass = $false
}

$result.finished_at = (Get-Date).ToString("o")
Write-ResultJson -Payload $result -Path $LogPath
Write-Output ("AUTH_PROBE_LOG=" + $LogPath)
Write-Output ("AUTH_PROBE_PASS=" + $result.pass)
if ($result.error) {
    Write-Output ("AUTH_PROBE_ERROR=" + $result.error)
}
