param(
    [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"

function Test-Step {
    param(
        [string]$Name,
        [scriptblock]$Action
    )

    try {
        & $Action
        Write-Host "[PASS] $Name" -ForegroundColor Green
    } catch {
        Write-Host "[FAIL] $Name -> $($_.Exception.Message)" -ForegroundColor Red
        throw
    }
}

Write-Host "Running local production-simulation validation..." -ForegroundColor Cyan

Test-Step "PHP version is 8.2 in container" {
    $v = docker compose exec -T web php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"
    if ($v.Trim() -ne "8.2") { throw "Expected 8.2, got $v" }
}

Test-Step "Required PHP extensions present" {
    $required = @("pdo", "pdo_mysql", "mbstring", "openssl", "curl", "fileinfo")
    $mods = docker compose exec -T web php -m | ForEach-Object { $_.Trim().ToLower() }
    foreach ($ext in $required) {
        if ($mods -notcontains $ext) { throw "Missing extension: $ext" }
    }
}

Test-Step "Composer install succeeds" {
    docker compose exec -T web composer install --no-interaction --prefer-dist | Out-Null
}

Test-Step "Composer platform requirements pass" {
    docker compose exec -T web composer check-platform-reqs | Out-Null
}

Test-Step "Production env flags effective" {
    $envCheck = docker compose exec -T web sh -lc 'echo "$APP_ENV,$APP_DEBUG"'
    if ($envCheck.Trim() -ne "production,false") {
        throw "Expected APP_ENV=production and APP_DEBUG=false, got $envCheck"
    }
}

Test-Step "Admin endpoint reachable" {
    $r = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/admin" -MaximumRedirection 5
    if ($r.StatusCode -lt 200 -or $r.StatusCode -ge 400) { throw "Unexpected status: $($r.StatusCode)" }
}

Test-Step "Legacy admin login page reachable" {
    $r = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/admin/login.php"
    if ($r.Content -notmatch "Admin Login") { throw "Login page content not detected" }
}

Test-Step "Legacy product slug behavior" {
    try {
        $r = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/product/new-york-chocolate-baked-cheesecake" -MaximumRedirection 0 -ErrorAction Stop
        if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 400) {
            return
        }
        throw "Unexpected success status for legacy slug: $($r.StatusCode)"
    } catch {
        $resp = $_.Exception.Response
        if ($resp) {
            $code = $resp.StatusCode.value__
            if ($code -eq 301 -or $code -eq 302 -or $code -eq 404) {
                return
            }
            throw "Unexpected response for legacy slug: $code"
        }
        throw
    }
}

Write-Host "Validation completed." -ForegroundColor Cyan
