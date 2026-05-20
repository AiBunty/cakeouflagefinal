$ErrorActionPreference = 'Stop'

$base = 'http://127.0.0.1:8080'
$cookie = Join-Path $PSScriptRoot 'wave3-smoke.cookie.txt'
if (Test-Path $cookie) { Remove-Item $cookie -Force }

function Invoke-Json {
    param(
        [string]$Method,
        [string]$Url,
        [hashtable]$Body = @{},
        [switch]$UseCookie
    )

    $args = @('-s', '-i', '-X', $Method)
    if ($UseCookie) {
        $args += @('-b', $cookie, '-c', $cookie)
    }

    if ($Method -ne 'GET') {
        $args += @('-H', 'Content-Type: application/json', '--data-raw', ($Body | ConvertTo-Json -Depth 10 -Compress))
    }

    $args += $Url
    $raw = & curl.exe @args

    $parts = $raw -split "`r?`n`r?`n", 2
    $headers = $parts[0]
    $responseBody = if ($parts.Count -gt 1) { $parts[1] } else { '' }
    $status = 0
    if ($headers -match 'HTTP/\S+\s+(\d{3})') {
        $status = [int]$Matches[1]
    }

    return [pscustomobject]@{
        Status = $status
        Body = $responseBody
    }
}

Write-Output 'Checking DB-backed API availability...'
$dbCheck = Invoke-Json -Method 'GET' -Url "$base/api/catalog/courses"
if ($dbCheck.Status -eq 503) {
    Write-Output 'DB unavailable (503). Connect local/staging DB and rerun this script.'
    exit 2
}

$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$email = "wave3+$stamp@example.com"
$password = 'Wave3Test!123'
$phone = '9999999999'

Write-Output 'Registering customer account...'
$register = Invoke-Json -Method 'POST' -Url "$base/api/auth/register" -Body @{
    full_name = 'Wave3 Smoke User'
    email = $email
    phone = $phone
    password = $password
} -UseCookie
Write-Output "register status=$($register.Status)"

Write-Output 'Logging in customer account...'
$login = Invoke-Json -Method 'POST' -Url "$base/api/auth/login" -Body @{
    email = $email
    password = $password
} -UseCookie
Write-Output "login status=$($login.Status)"

$checks = @(
    '/api/auth/me',
    '/api/account/profile',
    '/api/account/addresses',
    '/api/orders',
    '/api/wishlist',
    '/api/catalog/courses'
)

foreach ($path in $checks) {
    $response = Invoke-Json -Method 'GET' -Url "$base$path" -UseCookie
    Write-Output "$path -> $($response.Status)"
}

Write-Output 'Submitting contact inquiry...'
$contact = Invoke-Json -Method 'POST' -Url "$base/api/inquiries/contact" -Body @{
    name = 'Wave3 Contact'
    email = $email
    phone = $phone
    subject = 'Smoke test'
    message = 'Contact inquiry smoke test'
} -UseCookie
Write-Output "contact inquiry status=$($contact.Status)"

Write-Output 'Submitting course inquiry...'
$courseInquiry = Invoke-Json -Method 'POST' -Url "$base/api/inquiries/course" -Body @{
    name = 'Wave3 Learner'
    phone = $phone
    email = $email
    workshop = 'Beginner Cake Workshop'
    preferred_date = 'April 2026'
    message = 'Course inquiry smoke test'
} -UseCookie
Write-Output "course inquiry status=$($courseInquiry.Status)"

Write-Output 'Logging out customer...'
$logout = Invoke-Json -Method 'POST' -Url "$base/api/auth/logout" -Body @{} -UseCookie
Write-Output "logout status=$($logout.Status)"

Write-Output 'Wave 3 authenticated smoke test completed.'
