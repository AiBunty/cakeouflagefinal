param(
    [string]$BaseUrl = 'https://cakeouflage.com',
    [System.Management.Automation.PSCredential]$AdminCredential,
    [System.Management.Automation.PSCredential]$UserCredential
)

$ErrorActionPreference = 'Stop'

function Invoke-Json {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('GET','POST','PATCH','DELETE')] [string]$Method,
        [Parameter(Mandatory = $true)][string]$Url,
        [hashtable]$Body,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [string]$CsrfToken = ''
    )

    $headers = @{ 'Accept' = 'application/json' }
    if ($Method -ne 'GET' -and $CsrfToken) {
        $headers['X-CSRF-Token'] = $CsrfToken
    }

    $params = @{ Method = $Method; Uri = $Url; Headers = $headers }
    if ($Session) { $params.WebSession = $Session }
    if ($Body) {
        if ($Method -ne 'GET' -and $CsrfToken -and -not $Body.ContainsKey('_csrf')) {
            $Body['_csrf'] = $CsrfToken
        }
        $params.ContentType = 'application/json'
        $params.Body = ($Body | ConvertTo-Json -Depth 8)
    }

    try {
        $response = Invoke-RestMethod @params
        return @{ ok = $true; data = $response }
    } catch {
        $msg = $_.Exception.Message
        return @{ ok = $false; error = $msg }
    }
}

function Get-CsrfToken {
    param(
        [Parameter(Mandatory = $true)][string]$BaseUrl,
        [Parameter(Mandatory = $true)][Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    try {
        $response = Invoke-WebRequest -Method GET -Uri ($BaseUrl + '/login') -WebSession $Session
        $html = [string]$response.Content
        $match = [regex]::Match($html, '<meta\s+name="csrf-token"\s+content="([^"]+)"')
        if ($match.Success) {
            return $match.Groups[1].Value
        }
    } catch {
        return ''
    }
    return ''
}

$publicChecks = @(
    '/api/health',
    '/api/catalog/categories',
    '/api/catalog/products?limit=5',
    '/api/catalog/courses',
    '/api/catalog/events'
)

Write-Host "Public API checks:"
foreach ($path in $publicChecks) {
    $result = Invoke-Json -Method GET -Url ($BaseUrl + $path)
    if ($result.ok) {
        Write-Host "PASS $path"
    } else {
        Write-Host "FAIL $path -> $($result.error)"
    }
}

if ($UserCredential) {
    $userSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $userCsrf = Get-CsrfToken -BaseUrl $BaseUrl -Session $userSession
    Write-Host "\nUser auth checks:"
    $login = Invoke-Json -Method POST -Url ($BaseUrl + '/api/auth/login') -Body @{ email = $UserCredential.UserName; password = $UserCredential.GetNetworkCredential().Password } -Session $userSession -CsrfToken $userCsrf
    if ($login.ok) {
        Write-Host 'PASS user login'
        $me = Invoke-Json -Method GET -Url ($BaseUrl + '/api/auth/me') -Session $userSession -CsrfToken $userCsrf
        Write-Host (($me.ok) ? 'PASS /api/auth/me' : "FAIL /api/auth/me -> $($me.error)")
        $orders = Invoke-Json -Method GET -Url ($BaseUrl + '/api/orders') -Session $userSession -CsrfToken $userCsrf
        Write-Host (($orders.ok) ? 'PASS /api/orders' : "FAIL /api/orders -> $($orders.error)")
        $wishlist = Invoke-Json -Method GET -Url ($BaseUrl + '/api/wishlist') -Session $userSession -CsrfToken $userCsrf
        Write-Host (($wishlist.ok) ? 'PASS /api/wishlist' : "FAIL /api/wishlist -> $($wishlist.error)")
    } else {
        Write-Host "FAIL user login -> $($login.error)"
    }
}

if ($AdminCredential) {
    $adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $adminCsrf = Get-CsrfToken -BaseUrl $BaseUrl -Session $adminSession
    Write-Host "\nAdmin auth checks:"
    $login = Invoke-Json -Method POST -Url ($BaseUrl + '/api/admin/auth/login') -Body @{ email = $AdminCredential.UserName; password = $AdminCredential.GetNetworkCredential().Password } -Session $adminSession -CsrfToken $adminCsrf
    if ($login.ok) {
        Write-Host 'PASS admin login'
        $events = Invoke-Json -Method GET -Url ($BaseUrl + '/api/admin/events') -Session $adminSession -CsrfToken $adminCsrf
        Write-Host (($events.ok) ? 'PASS /api/admin/events' : "FAIL /api/admin/events -> $($events.error)")
        $courses = Invoke-Json -Method GET -Url ($BaseUrl + '/api/admin/courses') -Session $adminSession -CsrfToken $adminCsrf
        Write-Host (($courses.ok) ? 'PASS /api/admin/courses' : "FAIL /api/admin/courses -> $($courses.error)")
        $reports = Invoke-Json -Method GET -Url ($BaseUrl + '/api/admin/reports/summary') -Session $adminSession -CsrfToken $adminCsrf
        Write-Host (($reports.ok) ? 'PASS /api/admin/reports/summary' : "FAIL /api/admin/reports/summary -> $($reports.error)")
    } else {
        Write-Host "FAIL admin login -> $($login.error)"
    }
}
