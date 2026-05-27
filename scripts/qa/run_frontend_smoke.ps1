param(
    [string]$BaseUrl = "http://localhost:8080",
    [string]$LogPath = "storage/logs/qa_frontend_smoke_local.json"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$productSlug = ""
try {
    $productSlug = (docker exec cakeouflage-db mariadb -uroot -proot -D cakeouflage_local -N -B -e "SELECT slug FROM products WHERE deleted_at IS NULL AND slug IS NOT NULL AND slug <> '' ORDER BY id ASC LIMIT 1;").Trim()
} catch {
    $productSlug = ""
}

if ([string]::IsNullOrWhiteSpace($productSlug)) {
    $productSlug = "chocolate-truffle-cake"
}

$routes = @(
    @{ name = 'homepage'; path = '/' },
    @{ name = 'category'; path = '/category' },
    @{ name = 'product_sample'; path = ('/product/' + $productSlug) },
    @{ name = 'cart'; path = '/cart' },
    @{ name = 'checkout'; path = '/checkout' },
    @{ name = 'login'; path = '/login' },
    @{ name = 'account'; path = '/account' },
    @{ name = 'wishlist'; path = '/wishlist' },
    @{ name = 'orders'; path = '/orders' }
)

$results = [ordered]@{
    started_at = (Get-Date).ToString('o')
    base_url = $BaseUrl
    pass = $true
    checks = @()
}

foreach ($route in $routes) {
    $url = $BaseUrl.TrimEnd('/') + $route.path
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 25
        $status = [int]$resp.StatusCode
        $isPass = ($status -eq 200)

        $results.checks += [ordered]@{
            name = $route.name
            path = $route.path
            status = $status
            pass = $isPass
            title = [regex]::Match($resp.Content, '<title>(.*?)</title>', 'IgnoreCase').Groups[1].Value
        }

        if (-not $isPass) {
            $results.pass = $false
        }
    } catch {
        $status = 0
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $status = [int]$_.Exception.Response.StatusCode
        }

        $results.checks += [ordered]@{
            name = $route.name
            path = $route.path
            status = $status
            pass = $false
            title = ''
            error = $_.Exception.Message
        }
        $results.pass = $false
    }
}

$results.finished_at = (Get-Date).ToString('o')

$dir = Split-Path -Parent $LogPath
if ($dir -and -not (Test-Path $dir)) {
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
}

$results | ConvertTo-Json -Depth 8 | Set-Content -Path $LogPath -Encoding UTF8
Write-Output ("FRONTEND_SMOKE_LOG=" + $LogPath)
Write-Output ("FRONTEND_SMOKE_PASS=" + $results.pass)
