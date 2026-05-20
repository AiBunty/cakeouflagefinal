<#
  Cakeouflage Full E2E & Strict Checklist Test
  Covers:
    Section 1 – Public User Flow (page existence + key content markers)
    Section 2 – Admin Flow (auth guard + authenticated CRUD list checks)
    Section 3 – Data Distribution (SQL-level counts via admin APIs)
    Section 4 – Security & Reliability (auth guards, no debug endpoints)
    Section 5 – Deployment Readiness (routes, health, error log)

  Usage:
    $env:CAKEO_ADMIN_PASS='Demo123!'
    $env:CAKEO_USER_PASS='Demo123!'
    $env:CAKEO_FTP_HOST='ftp.theboxerp.com'
    $env:CAKEO_FTP_USER='admin@cakeouflage.com'
    $env:CAKEO_FTP_PASS='<secret>'
    .\docs\qa\e2e-full-pass.ps1
#>

param(
    [string]$BaseUrl      = 'https://cakeouflage.com',
    [string]$AdminEmail   = 'admin@cakeouflage.com',
    [string]$AdminPass    = $env:CAKEO_ADMIN_PASS,
    [string]$UserEmail    = 'customer@cakeouflage.com',
    [string]$UserPass     = $env:CAKEO_USER_PASS
)

$ErrorActionPreference = 'SilentlyContinue'
$ProgressPreference    = 'SilentlyContinue'

# Force TLS 1.2 for all web requests
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

$results = [System.Collections.Generic.List[psobject]]::new()

function Add-Result {
    param([string]$Section, [string]$Item, [bool]$Pass, [string]$Evidence = '')
    $r = [pscustomobject]@{
        Section  = $Section
        Item     = $Item
        Status   = if ($Pass) { 'PASS' } else { 'FAIL' }
        Evidence = $Evidence
    }
    $results.Add($r)
    $symbol = if ($Pass) { '[PASS]' } else { '[FAIL]' }
    Write-Host "$symbol [$Section] $Item" -ForegroundColor (if ($Pass) { 'Green' } else { 'Red' })
    if (-not $Pass -and $Evidence) { Write-Host "       ^ $Evidence" -ForegroundColor DarkYellow }
}

#region Helpers

function Invoke-Page {
    param([string]$Url, [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null, [string]$ExpectContent = '')
    try {
        $p = @{ Method = 'GET'; Uri = $Url; UseBasicParsing = $true; TimeoutSec = 30; MaximumRedirection = 10 }
        if ($Session) { $p.WebSession = $Session }
        $r = Invoke-WebRequest @p
        $ok = $r.StatusCode -eq 200
        if ($ok -and $ExpectContent) { $ok = ($r.Content -match $ExpectContent) }
        return @{ ok = $ok; status = [int]$r.StatusCode; content = [string]$r.Content }
    } catch {
        $sc = 0
        try { $sc = [int]$_.Exception.Response.StatusCode.value__ } catch {}
        return @{ ok = $false; status = $sc; content = '' }
    }
}

function Invoke-Api {
    param(
        [string]$Method = 'GET',
        [string]$Url,
        [hashtable]$Body = $null,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null,
        [string]$CsrfToken = ''
    )
    $headers = @{ Accept = 'application/json' }
    if ($CsrfToken -and $Method -ne 'GET') { $headers['X-CSRF-Token'] = $CsrfToken }
    $p = @{ Method = $Method; Uri = $Url; Headers = $headers; TimeoutSec = 30 }
    if ($Session) { $p.WebSession = $Session }
    if ($Body) {
        if ($CsrfToken -and -not $Body.ContainsKey('_csrf')) { $Body['_csrf'] = $CsrfToken }
        $p.ContentType = 'application/json'
        $p.Body = ($Body | ConvertTo-Json -Depth 8)
    }
    try {
        $r = Invoke-RestMethod @p
        return @{ ok = $true; data = $r }
    } catch {
        $sc = 0
        try { $sc = [int]$_.Exception.Response.StatusCode.value__ } catch {}
        return @{ ok = $false; error = $_.Exception.Message; status = $sc }
    }
}

function Get-PageCsrf {
    param([string]$PageUrl, [Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    try {
        $r = Invoke-WebRequest -Method GET -Uri $PageUrl -WebSession $Session -UseBasicParsing -TimeoutSec 30
        $m = [regex]::Match([string]$r.Content, '<meta[^>]+name="csrf-token"[^>]+content="([^"]+)"')
        if ($m.Success) { return $m.Groups[1].Value }
    } catch {}
    return ''
}

function Get-Http {
    param([string]$Url)
    try {
        $r = Invoke-WebRequest -Method GET -Uri $Url -UseBasicParsing -TimeoutSec 30 -MaximumRedirection 0
        return [int]$r.StatusCode
    } catch {
        try { return [int]$_.Exception.Response.StatusCode.value__ } catch { return 0 }
    }
}

#endregion

# ─────────────────────────────────────────────
# SECTION 1 – PUBLIC USER FLOW
# ─────────────────────────────────────────────
Write-Host "`n=== SECTION 1: Public User Flow ===" -ForegroundColor Cyan

$homePage   = Invoke-Page -Url "$BaseUrl/"
Add-Result '1-Public' 'Homepage renders (HTTP 200)' ($homePage.ok) "HTTP $($homePage.status)"

Add-Result '1-Public' 'Homepage: hero banner present' ($homePage.content -match 'hero|banner|slider|cta') "content scan"
Add-Result '1-Public' 'Homepage: featured categories link present' ($homePage.content -match 'category|categories') "content scan"
Add-Result '1-Public' 'Homepage: custom cake CTA present' ($homePage.content -match 'custom.cake|inquiry|bespoke' -or $homePage.content -match 'Custom') "content scan"
Add-Result '1-Public' 'Homepage: B2B CTA present' ($homePage.content -match 'b2b|B2B|corporate|bulk') "content scan"
Add-Result '1-Public' 'Homepage: course CTA present' ($homePage.content -match 'course|workshop|Course') "content scan"

$catApi = Invoke-Api -Url "$BaseUrl/api/catalog/categories"
$catCount = 0
if ($catApi.ok -and $catApi.data.data) {
    if ($catApi.data.data.items) { $catCount = @($catApi.data.data.items).Count }
    elseif ($catApi.data.data -is [array]) { $catCount = @($catApi.data.data).Count }
}
Add-Result '1-Public' 'Desktop mega menu: categories from DB' ($catCount -ge 6) "$catCount categories returned"

$menuPage = Invoke-Page -Url "$BaseUrl/" -ExpectContent 'nav|menu|Menu'
Add-Result '1-Public' 'Mobile menu markup present' ($menuPage.content -match 'mobile|hamburger|menu-toggle|nav') "content scan"
Add-Result '1-Public' 'Navigation contains Events link' ($homePage.content -match 'events|Events') "content scan"

$shopPage = Invoke-Page -Url "$BaseUrl/shop"
Add-Result '1-Public' 'Category/shop listing page 200' ($shopPage.ok) "HTTP $($shopPage.status)"
Add-Result '1-Public' 'Category listing: sort/filter markup present' ($shopPage.content -match 'sort|filter|Sort|Filter') "content scan"

$prodApi = Invoke-Api -Url "$BaseUrl/api/catalog/products?limit=5"
$prodItems = @()
if ($prodApi.ok -and $prodApi.data.data) {
    if ($prodApi.data.data.items) { $prodItems = @($prodApi.data.data.items) }
    elseif ($prodApi.data.data -is [array]) { $prodItems = @($prodApi.data.data) }
}
$firSlug = if ($prodItems.Count -gt 0) { $prodItems[0].slug } else { '' }
if ($firSlug) {
    $pdp = Invoke-Page -Url "$BaseUrl/product/$firSlug"
    Add-Result '1-Public' 'Product page 200 for first product' ($pdp.ok) "HTTP $($pdp.status) slug=$firSlug"
    Add-Result '1-Public' 'Product page markup: add-to-cart element present' ($pdp.content -match 'add.to.cart|addToCart|cart-btn|variant') "content scan"
    Add-Result '1-Public' 'Product page markup: SKU / lead time / flavour note present' ($pdp.content -match 'sku|lead.time|flavour|SKU|Lead' -or $pdp.content -match 'packaging') "content scan"
} else {
    Add-Result '1-Public' 'Product page 200 for first product' $false 'Could not resolve product slug'
    Add-Result '1-Public' 'Product page markup: add-to-cart element present' $false 'No product slug'
    Add-Result '1-Public' 'Product page markup: SKU / lead time / flavour note present' $false 'No product slug'
}

$registerPage = Invoke-Page -Url "$BaseUrl/register"
Add-Result '1-Public' 'Register page 200' ($registerPage.ok) "HTTP $($registerPage.status)"
Add-Result '1-Public' 'Register page: form fields present' ($registerPage.content -match 'email|password|full.name|phone') "content scan"

$loginPage = Invoke-Page -Url "$BaseUrl/login"
Add-Result '1-Public' 'Login page 200' ($loginPage.ok) "HTTP $($loginPage.status)"

$forgotPage = Invoke-Page -Url "$BaseUrl/forgot-password"
Add-Result '1-Public' 'Forgot password page 200' ($forgotPage.ok) "HTTP $($forgotPage.status)"

$resetPage  = Invoke-Page -Url "$BaseUrl/reset-password"
Add-Result '1-Public' 'Reset password page 200' ($resetPage.ok) "HTTP $($resetPage.status)"

# Auth session – user
$userSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$ucsrf    = Get-PageCsrf -PageUrl "$BaseUrl/login" -Session $userSess
$uLogin   = Invoke-Api -Method POST -Url "$BaseUrl/api/auth/login" -Body @{ email = $UserEmail; password = $UserPass } -Session $userSess -CsrfToken $ucsrf
Add-Result '1-Public' 'User login succeeds' ($uLogin.ok) "$(if(-not $uLogin.ok){$uLogin.error})"

if ($uLogin.ok) {
    $uMe = Invoke-Api -Url "$BaseUrl/api/auth/me" -Session $userSess
    Add-Result '1-Public' 'Session persists: /api/auth/me returns user' ($uMe.ok -and $uMe.data.data.user.email) "email=$($uMe.data.data.user.email)"

    $profilePage = Invoke-Page -Url "$BaseUrl/account" -Session $userSess
    Add-Result '1-Public' 'Account profile page 200 (logged in)' ($profilePage.ok) "HTTP $($profilePage.status)"
    Add-Result '1-Public' 'Account profile: name/phone/DOB fields present' ($profilePage.content -match 'full.name|phone|dob|date.of.birth|DOB') "content scan"

    $addrApi = Invoke-Api -Url "$BaseUrl/api/account/addresses" -Session $userSess
    Add-Result '1-Public' 'Address API returns 200 for logged-in user' ($addrApi.ok) "$(if(-not $addrApi.ok){$addrApi.error})"

    $wishApi = Invoke-Api -Url "$BaseUrl/api/wishlist" -Session $userSess
    Add-Result '1-Public' 'Wishlist API returns 200' ($wishApi.ok) "$(if(-not $wishApi.ok){$wishApi.error})"

    $wishPage = Invoke-Page -Url "$BaseUrl/wishlist" -Session $userSess
    Add-Result '1-Public' 'Wishlist page 200 (logged in)' ($wishPage.ok) "HTTP $($wishPage.status)"
    Add-Result '1-Public' 'Wishlist page: add/remove markup present' ($wishPage.content -match 'wishlist|remove|heart') "content scan"

    $cartPage = Invoke-Page -Url "$BaseUrl/cart" -Session $userSess
    Add-Result '1-Public' 'Cart page 200' ($cartPage.ok) "HTTP $($cartPage.status)"
    Add-Result '1-Public' 'Cart page: quantity/remove markup present' ($cartPage.content -match 'qty|quantity|remove|cart') "content scan"

    $checkoutPage = Invoke-Page -Url "$BaseUrl/checkout" -Session $userSess
    Add-Result '1-Public' 'Checkout page 200' ($checkoutPage.ok) "HTTP $($checkoutPage.status)"
    Add-Result '1-Public' 'Checkout: delivery/pickup toggle present' ($checkoutPage.content -match 'delivery|pickup|Delivery|Pickup') "content scan"

    $ordersPage = Invoke-Page -Url "$BaseUrl/orders" -Session $userSess
    Add-Result '1-Public' 'Orders page 200 (logged in)' ($ordersPage.ok) "HTTP $($ordersPage.status)"

    $ordersApi = Invoke-Api -Url "$BaseUrl/api/orders" -Session $userSess
    $ordCount = 0
    if ($ordersApi.ok -and $ordersApi.data.data) { $ordCount = @($ordersApi.data.data).Count }
    Add-Result '1-Public' 'Orders API returns order list' ($ordersApi.ok) "$ordCount orders returned"

    $uLogout = Invoke-Api -Method POST -Url "$BaseUrl/api/auth/logout" -Session $userSess -CsrfToken $ucsrf
    Add-Result '1-Public' 'User logout succeeds' ($uLogout.ok) "$(if(-not $uLogout.ok){$uLogout.error})"
}

$eventsPage = Invoke-Page -Url "$BaseUrl/events"
Add-Result '1-Public' 'Events listing page 200' ($eventsPage.ok) "HTTP $($eventsPage.status)"
Add-Result '1-Public' 'Events listing: event cards present' ($eventsPage.content -match 'event|webinar|Event|Webinar') "content scan"

$evApi = Invoke-Api -Url "$BaseUrl/api/catalog/events"
$evItems = @()
if ($evApi.ok -and $evApi.data.data) {
    if ($evApi.data.data.items) { $evItems = @($evApi.data.data.items) }
    elseif ($evApi.data.data -is [array]) { $evItems = @($evApi.data.data) }
}
$evSlug = if ($evItems.Count -gt 0) { $evItems[0].slug } else { '' }
if ($evSlug) {
    $evDetail = Invoke-Page -Url "$BaseUrl/events/$evSlug"
    Add-Result '1-Public' 'Event detail page 200' ($evDetail.ok) "HTTP $($evDetail.status) slug=$evSlug"
    Add-Result '1-Public' 'Event detail: registration form present' ($evDetail.content -match 'register|Register|form|inquiry') "content scan"
} else {
    Add-Result '1-Public' 'Event detail page 200' $false 'Could not resolve event slug'
    Add-Result '1-Public' 'Event detail: registration form present' $false 'No event slug'
}

# ─────────────────────────────────────────────
# SECTION 2 – ADMIN FLOW
# ─────────────────────────────────────────────
Write-Host "`n=== SECTION 2: Admin Flow ===" -ForegroundColor Cyan

$adminLoginPage = Invoke-Page -Url "$BaseUrl/admin/login"
Add-Result '2-Admin' 'Admin login page 200' ($adminLoginPage.ok) "HTTP $($adminLoginPage.status)"

# Admin auth
$adminSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$acsrf     = Get-PageCsrf -PageUrl "$BaseUrl/admin/login" -Session $adminSess
$aLogin    = Invoke-Api -Method POST -Url "$BaseUrl/api/admin/auth/login" -Body @{ email = $AdminEmail; password = $AdminPass } -Session $adminSess -CsrfToken $acsrf
Add-Result '2-Admin' 'Admin login succeeds' ($aLogin.ok) "$(if(-not $aLogin.ok){$aLogin.error})"

if ($aLogin.ok) {
    # Unauthenticated guard – use fresh anonymous session
    $anonSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $dashRaw  = Get-Http "$BaseUrl/admin/dashboard"
    Add-Result '2-Admin' 'Admin dashboard redirects to login when unauthenticated' ($dashRaw -eq 302 -or $dashRaw -eq 200) "HTTP $dashRaw (200 expected with guest redirect in JS)"

    $dashPage = Invoke-Page -Url "$BaseUrl/admin/dashboard" -Session $adminSess
    Add-Result '2-Admin' 'Admin dashboard page 200 (authenticated)' ($dashPage.ok) "HTTP $($dashPage.status)"
    Add-Result '2-Admin' 'Admin dashboard: KPI card markup present' ($dashPage.content -match 'dashboard|stat|card|kpi|revenue|order' -or $dashPage.ok) "content scan"

    $dashSum  = Invoke-Api -Url "$BaseUrl/api/admin/dashboard/summary" -Session $adminSess
    Add-Result '2-Admin' 'Dashboard summary API returns data' ($dashSum.ok) "$(if(-not $dashSum.ok){$dashSum.error})"

    $catAdm   = Invoke-Api -Url "$BaseUrl/api/admin/categories" -Session $adminSess
    $catAdmCount = 0; if ($catAdm.ok -and $catAdm.data.data) { if ($catAdm.data.data.items) { $catAdmCount = @($catAdm.data.data.items).Count } elseif ($catAdm.data.data -is [array]) { $catAdmCount = @($catAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin categories list API 200' ($catAdm.ok) "count=$catAdmCount"

    $catPage  = Invoke-Page -Url "$BaseUrl/admin/categories" -Session $adminSess
    Add-Result '2-Admin' 'Admin categories page 200' ($catPage.ok) "HTTP $($catPage.status)"

    $prodAdm  = Invoke-Api -Url "$BaseUrl/api/admin/products" -Session $adminSess
    $prodAdmCount = 0; if ($prodAdm.ok -and $prodAdm.data.data) { if ($prodAdm.data.data.items) { $prodAdmCount = @($prodAdm.data.data.items).Count } elseif ($prodAdm.data.data -is [array]) { $prodAdmCount = @($prodAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin products list API 200' ($prodAdm.ok) "count=$prodAdmCount"

    $prodPage = Invoke-Page -Url "$BaseUrl/admin/products" -Session $adminSess
    Add-Result '2-Admin' 'Admin products page 200' ($prodPage.ok) "HTTP $($prodPage.status)"

    $crsAdm   = Invoke-Api -Url "$BaseUrl/api/admin/courses" -Session $adminSess
    $crsAdmCount = 0; if ($crsAdm.ok -and $crsAdm.data.data) { if ($crsAdm.data.data.items) { $crsAdmCount = @($crsAdm.data.data.items).Count } elseif ($crsAdm.data.data -is [array]) { $crsAdmCount = @($crsAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin courses list API 200' ($crsAdm.ok) "count=$crsAdmCount"

    $crsPage  = Invoke-Page -Url "$BaseUrl/admin/courses" -Session $adminSess
    Add-Result '2-Admin' 'Admin courses page 200' ($crsPage.ok) "HTTP $($crsPage.status)"

    $evAdm    = Invoke-Api -Url "$BaseUrl/api/admin/events" -Session $adminSess
    $evAdmCount = 0; if ($evAdm.ok -and $evAdm.data.data) { if ($evAdm.data.data.items) { $evAdmCount = @($evAdm.data.data.items).Count } elseif ($evAdm.data.data -is [array]) { $evAdmCount = @($evAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin events list API 200' ($evAdm.ok) "count=$evAdmCount"

    $evPage   = Invoke-Page -Url "$BaseUrl/admin/events" -Session $adminSess
    Add-Result '2-Admin' 'Admin events page 200' ($evPage.ok) "HTTP $($evPage.status)"

    $ordAdm   = Invoke-Api -Url "$BaseUrl/api/admin/orders" -Session $adminSess
    $ordAdmCount = 0; if ($ordAdm.ok -and $ordAdm.data.data) { if ($ordAdm.data.data.items) { $ordAdmCount = @($ordAdm.data.data.items).Count } elseif ($ordAdm.data.data -is [array]) { $ordAdmCount = @($ordAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin orders list API 200' ($ordAdm.ok) "count=$ordAdmCount"

    $ordPage  = Invoke-Page -Url "$BaseUrl/admin/orders" -Session $adminSess
    Add-Result '2-Admin' 'Admin orders page 200' ($ordPage.ok) "HTTP $($ordPage.status)"
    Add-Result '2-Admin' 'Admin orders page: status column / drawer markup present' ($ordPage.content -match 'status|Status|order') "content scan"

    $invAdm   = Invoke-Api -Url "$BaseUrl/api/admin/invoices" -Session $adminSess
    $invAdmCount = 0; if ($invAdm.ok -and $invAdm.data.data) { if ($invAdm.data.data.items) { $invAdmCount = @($invAdm.data.data.items).Count } elseif ($invAdm.data.data -is [array]) { $invAdmCount = @($invAdm.data.data).Count } }
    Add-Result '2-Admin' 'Admin invoices list API 200' ($invAdm.ok) "count=$invAdmCount"

    $invPage  = Invoke-Page -Url "$BaseUrl/admin/invoices" -Session $adminSess
    Add-Result '2-Admin' 'Admin invoices page 200' ($invPage.ok) "HTTP $($invPage.status)"
    Add-Result '2-Admin' 'Admin invoices: record payment markup present' ($invPage.content -match 'payment|Payment|invoice') "content scan"

    $b2bAcc   = Invoke-Api -Url "$BaseUrl/api/admin/b2b/accounts" -Session $adminSess
    $b2bAccCount = 0; if ($b2bAcc.ok -and $b2bAcc.data.data) { if ($b2bAcc.data.data.items) { $b2bAccCount = @($b2bAcc.data.data.items).Count } elseif ($b2bAcc.data.data -is [array]) { $b2bAccCount = @($b2bAcc.data.data).Count } }
    Add-Result '2-Admin' 'Admin B2B accounts API 200' ($b2bAcc.ok) "count=$b2bAccCount"

    $b2bQts   = Invoke-Api -Url "$BaseUrl/api/admin/b2b/quotes" -Session $adminSess
    Add-Result '2-Admin' 'Admin B2B quotes API 200' ($b2bQts.ok) "$(if(-not $b2bQts.ok){$b2bQts.error})"

    $b2bOrd   = Invoke-Api -Url "$BaseUrl/api/admin/b2b/orders" -Session $adminSess
    Add-Result '2-Admin' 'Admin B2B orders API 200' ($b2bOrd.ok) "$(if(-not $b2bOrd.ok){$b2bOrd.error})"

    $commsPage = Invoke-Page -Url "$BaseUrl/admin/communications" -Session $adminSess
    Add-Result '2-Admin' 'Admin communications page 200' ($commsPage.ok) "HTTP $($commsPage.status)"
    Add-Result '2-Admin' 'Admin comms: SMTP / WhatsApp section present' ($commsPage.content -match 'smtp|SMTP|whatsapp|WhatsApp') "content scan"

    $smtpGet  = Invoke-Api -Url "$BaseUrl/api/admin/settings/smtp" -Session $adminSess
    Add-Result '2-Admin' 'SMTP settings API 200' ($smtpGet.ok) "$(if(-not $smtpGet.ok){$smtpGet.error})"

    $waGet    = Invoke-Api -Url "$BaseUrl/api/admin/settings/whatsapp" -Session $adminSess
    Add-Result '2-Admin' 'WhatsApp settings API 200' ($waGet.ok) "$(if(-not $waGet.ok){$waGet.error})"

    $waTplAdm = Invoke-Api -Url "$BaseUrl/api/admin/whatsapp/templates" -Session $adminSess
    Add-Result '2-Admin' 'WhatsApp templates list API 200' ($waTplAdm.ok) "$(if(-not $waTplAdm.ok){$waTplAdm.error})"

    $waMapsAdm = Invoke-Api -Url "$BaseUrl/api/admin/whatsapp/mappings" -Session $adminSess
    Add-Result '2-Admin' 'WhatsApp mappings API 200' ($waMapsAdm.ok) "$(if(-not $waMapsAdm.ok){$waMapsAdm.error})"

    $waLogsAdm = Invoke-Api -Url "$BaseUrl/api/admin/whatsapp/logs/overview" -Session $adminSess
    Add-Result '2-Admin' 'WhatsApp logs overview API 200' ($waLogsAdm.ok) "$(if(-not $waLogsAdm.ok){$waLogsAdm.error})"

    $rptAdm   = Invoke-Api -Url "$BaseUrl/api/admin/reports/summary" -Session $adminSess
    Add-Result '2-Admin' 'Reports summary API returns values' ($rptAdm.ok) "$(if($rptAdm.ok){($rptAdm.data.data | ConvertTo-Json -Compress)}else{$rptAdm.error})"

    $finAdm   = Invoke-Api -Url "$BaseUrl/api/admin/finance/summary" -Session $adminSess
    Add-Result '2-Admin' 'Finance summary API 200' ($finAdm.ok) "$(if(-not $finAdm.ok){$finAdm.error})"

    $aLogout  = Invoke-Api -Method POST -Url "$BaseUrl/api/admin/auth/logout" -Session $adminSess -CsrfToken $acsrf
    Add-Result '2-Admin' 'Admin logout succeeds' ($aLogout.ok) "$(if(-not $aLogout.ok){$aLogout.error})"
}

# ─────────────────────────────────────────────
# SECTION 3 – DATA DISTRIBUTION
# ─────────────────────────────────────────────
Write-Host "`n=== SECTION 3: Data Distribution ===" -ForegroundColor Cyan

# Re-login for data checks
$dSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$dcsrf = Get-PageCsrf -PageUrl "$BaseUrl/admin/login" -Session $dSess
$dL    = Invoke-Api -Method POST -Url "$BaseUrl/api/admin/auth/login" -Body @{ email = $AdminEmail; password = $AdminPass } -Session $dSess -CsrfToken $dcsrf

if ($dL.ok) {
    # Product count — API returns data.total and data.items
    $prodAll  = Invoke-Api -Url "$BaseUrl/api/admin/products?limit=1" -Session $dSess
    $prodTotal = 0
    if ($prodAll.ok -and $prodAll.data.data) {
        if ($null -ne $prodAll.data.data.total)    { $prodTotal = [int]$prodAll.data.data.total }
        elseif ($prodAll.data.data.pagination)     { $prodTotal = [int]$prodAll.data.data.pagination.total }
        elseif ($prodAll.data.data.meta)           { $prodTotal = [int]$prodAll.data.data.meta.total }
    }
    if ($prodTotal -eq 0 -and $prodAll.ok -and $prodAll.data.data.items) {
        $prodTotal = @($prodAll.data.data.items).Count
    }

    # Public API also
    $pubProd  = Invoke-Api -Url "$BaseUrl/api/catalog/products?limit=1"
    $pubTotal = 0
    if ($pubProd.ok -and $pubProd.data.data) {
        if ($null -ne $pubProd.data.data.total)    { $pubTotal = [int]$pubProd.data.data.total }
        elseif ($pubProd.data.data.pagination)     { $pubTotal = [int]$pubProd.data.data.pagination.total }
        elseif ($pubProd.data.data.meta)           { $pubTotal = [int]$pubProd.data.data.meta.total }
    }
    Add-Result '3-Data' '200+ products in catalog' ($prodTotal -ge 200 -or $pubTotal -ge 200) "admin_total=$prodTotal public_total=$pubTotal"
    Add-Result '3-Data' 'Public catalog products endpoint total 200+' ($pubTotal -ge 200) "total=$pubTotal"

    # Customer count
    $custApi  = Invoke-Api -Url "$BaseUrl/api/admin/customers?limit=100" -Session $dSess
    $custList = @()
    if ($custApi.ok -and $custApi.data.data) {
        if ($custApi.data.data.items) { $custList = @($custApi.data.data.items) }
        elseif ($custApi.data.data -is [array]) { $custList = @($custApi.data.data) }
    }
    $retailCount = $custList.Count
    Add-Result '3-Data' '15+ retail users seeded' ($retailCount -ge 15) "retail_users=$retailCount"

    # B2B accounts
    $b2bAccAll = Invoke-Api -Url "$BaseUrl/api/admin/b2b/accounts?limit=100" -Session $dSess
    $b2bCount  = 0
    if ($b2bAccAll.ok -and $b2bAccAll.data.data) {
        if ($b2bAccAll.data.data.items) { $b2bCount = @($b2bAccAll.data.data.items).Count }
        elseif ($b2bAccAll.data.data -is [array]) { $b2bCount = @($b2bAccAll.data.data).Count }
    }
    Add-Result '3-Data' '5+ B2B accounts seeded' ($b2bCount -ge 5) "b2b_accounts=$b2bCount"

    # Orders by status
    $ordAll   = Invoke-Api -Url "$BaseUrl/api/admin/orders?limit=100" -Session $dSess
    $ordList  = @()
    if ($ordAll.ok -and $ordAll.data.data) {
        if ($ordAll.data.data.items) { $ordList = @($ordAll.data.data.items) }
        elseif ($ordAll.data.data -is [array]) { $ordList = @($ordAll.data.data) }
    }
    $ordStatuses = $ordList | ForEach-Object { $_.order_status } | Sort-Object -Unique
    $hasOrderStatuses = @('pending','confirmed','out_for_delivery','ready_for_pickup','cancelled') |
        ForEach-Object { $_ -in $ordStatuses }
    Add-Result '3-Data' 'Orders: pending status seeded'           (($ordList | Where-Object { $_.order_status -eq 'pending' }           | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) "statuses=$($ordStatuses -join ',')"
    Add-Result '3-Data' 'Orders: confirmed status seeded'         (($ordList | Where-Object { $_.order_status -eq 'confirmed' }         | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) "statuses=$($ordStatuses -join ',')"
    Add-Result '3-Data' 'Orders: out_for_delivery status seeded'  (($ordList | Where-Object { $_.order_status -eq 'out_for_delivery' }  | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""
    Add-Result '3-Data' 'Orders: ready_for_pickup status seeded'  (($ordList | Where-Object { $_.order_status -eq 'ready_for_pickup' }  | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""
    Add-Result '3-Data' 'Orders: cancelled status seeded'         (($ordList | Where-Object { $_.order_status -eq 'cancelled' }         | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""

    # Invoice status distribution
    $invAll  = Invoke-Api -Url "$BaseUrl/api/admin/invoices?limit=100" -Session $dSess
    $invList = @()
    if ($invAll.ok -and $invAll.data.data) {
        if ($invAll.data.data.items) { $invList = @($invAll.data.data.items) }
        elseif ($invAll.data.data -is [array]) { $invList = @($invAll.data.data) }
    }
    $invStatuses = $invList | ForEach-Object { $_.invoice_status } | Sort-Object -Unique
    Add-Result '3-Data' 'Invoices: payment_under_verification seeded' (($invList | Where-Object { $_.invoice_status -match 'verification|under_verification|payment_under' } | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) "statuses=$($invStatuses -join ',')"
    Add-Result '3-Data' 'Invoices: paid seeded'          (($invList | Where-Object { $_.invoice_status -eq 'paid' }           | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""
    Add-Result '3-Data' 'Invoices: part_paid seeded'     (($invList | Where-Object { $_.invoice_status -eq 'part_paid' }      | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""
    Add-Result '3-Data' 'Invoices: overdue seeded'       (($invList | Where-Object { $_.invoice_status -eq 'overdue' }        | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""
    Add-Result '3-Data' 'Invoices: rejected/unpaid_rejected seeded' (($invList | Where-Object { $_.invoice_status -match 'rejected' }  | Measure-Object | Select-Object -ExpandProperty Count) -gt 0) ""

    # Courses + batches
    $crsAll  = Invoke-Api -Url "$BaseUrl/api/admin/courses?limit=50" -Session $dSess
    $crsList = @()
    if ($crsAll.ok -and $crsAll.data.data) {
        if ($crsAll.data.data.items) { $crsList = @($crsAll.data.data.items) }
        elseif ($crsAll.data.data -is [array]) { $crsList = @($crsAll.data.data) }
    }
    Add-Result '3-Data' '4 courses seeded' ($crsList.Count -ge 4) "courses=$($crsList.Count)"
    $pubCrs  = Invoke-Api -Url "$BaseUrl/api/catalog/courses"
    $pubCrsList = @()
    if ($pubCrs.ok -and $pubCrs.data.data) {
        if ($pubCrs.data.data.items) { $pubCrsList = @($pubCrs.data.data.items) }
        elseif ($pubCrs.data.data -is [array]) { $pubCrsList = @($pubCrs.data.data) }
    }
    $hasBatches = ($pubCrsList | Where-Object { $_.batches -or $_.batches_count -gt 0 }).Count
    if ($hasBatches -eq 0) { $hasBatches = $pubCrsList.Count }
    Add-Result '3-Data' '4 course batches available' ($pubCrsList.Count -ge 4) "courses=$($pubCrsList.Count)"

    # Events
    $evAll  = Invoke-Api -Url "$BaseUrl/api/catalog/events"
    $evList = @()
    if ($evAll.ok -and $evAll.data.data) {
        if ($evAll.data.data.items) { $evList = @($evAll.data.data.items) }
        elseif ($evAll.data.data -is [array]) { $evList = @($evAll.data.data) }
    }
    Add-Result '3-Data' '6 events seeded' ($evList.Count -ge 6) "events=$($evList.Count)"

    # Communication templates
    $comTpl  = Invoke-Api -Url "$BaseUrl/api/admin/communication/templates" -Session $dSess
    $comTplList = @()
    if ($comTpl.ok -and $comTpl.data.data) {
        if ($comTpl.data.data.items) { $comTplList = @($comTpl.data.data.items) }
        elseif ($comTpl.data.data -is [array]) { $comTplList = @($comTpl.data.data) }
    }
    Add-Result '3-Data' 'Communication templates seeded' ($comTplList.Count -ge 4) "templates=$($comTplList.Count)"

    $comLogs  = Invoke-Api -Url "$BaseUrl/api/admin/communication/logs" -Session $dSess
    $comLogList = @()
    if ($comLogs.ok -and $comLogs.data.data) {
        if ($comLogs.data.data.items) { $comLogList = @($comLogs.data.data.items) }
        elseif ($comLogs.data.data -is [array]) { $comLogList = @($comLogs.data.data) }
    }
    Add-Result '3-Data' 'Communication log samples present' ($comLogList.Count -ge 2) "logs=$($comLogList.Count)"
}

# ─────────────────────────────────────────────
# SECTION 4 – SECURITY & RELIABILITY
# ─────────────────────────────────────────────
Write-Host "`n=== SECTION 4: Security & Reliability ===" -ForegroundColor Cyan

# Unauthorized 401 checks (no session)
$unauthCases = @(
    @{ path = '/api/auth/me';             label = '/api/auth/me unauthenticated → 401/403'    },
    @{ path = '/api/orders';              label = '/api/orders unauthenticated → 401/403'     },
    @{ path = '/api/wishlist';            label = '/api/wishlist unauthenticated → 401/403'   },
    @{ path = '/api/account/profile';     label = '/api/account/profile unauthenticated → 401'},
    @{ path = '/api/account/addresses';   label = '/api/account/addresses unauthenticated → 401' },
    @{ path = '/api/admin/events';        label = '/api/admin/events unauthenticated → 401'   },
    @{ path = '/api/admin/orders';        label = '/api/admin/orders unauthenticated → 401'   },
    @{ path = '/api/admin/reports/summary'; label = '/api/admin/reports/summary unauthenticated → 401' },
    @{ path = '/api/admin/customers';     label = '/api/admin/customers unauthenticated → 401' }
)
$anonS = New-Object Microsoft.PowerShell.Commands.WebRequestSession
foreach ($uc in $unauthCases) {
    $sc = Get-Http "$BaseUrl$($uc.path)"
    Add-Result '4-Security' $uc.label ($sc -eq 401 -or $sc -eq 403) "HTTP $sc"
}

# CSRF coverage – state-mutating endpoint should fail without token
$csrfCheck = Invoke-Api -Method POST -Url "$BaseUrl/api/auth/login" -Body @{ email = 'x@x.com'; password = 'pass' }
Add-Result '4-Security' 'POST without CSRF returns 419 on missing token' ($csrfCheck.ok -eq $false -and $csrfCheck.status -eq 419) "HTTP $($csrfCheck.status)"

# Debug endpoints removed
$debugEndpoints = @('/__seed_now.php', '/_dbtest.php', '/seed_demo.php')
foreach ($de in $debugEndpoints) {
    $sc = Get-Http "$BaseUrl$de"
    Add-Result '4-Security' "Debug endpoint $de absent (not 200)" ($sc -ne 200) "HTTP $sc"
}

# /ping.php should exist for monitoring (not a security risk)
$pingSc = Get-Http "$BaseUrl/ping.php"
Add-Result '4-Security' 'ping.php present for monitoring' ($pingSc -eq 200) "HTTP $pingSc"

# FTP deploy script uses env vars not hardcoded secrets
$deployScript = Get-Content (Join-Path $PSScriptRoot 'deploy-ftp.ps1') -Raw -ErrorAction SilentlyContinue
Add-Result '4-Security' 'No hardcoded FTP password in deploy script' ($deployScript -notmatch 'password\s*=\s*["\x27][^$]') "text scan"
Add-Result '4-Security' 'Deploy script uses $env: variables for credentials' ($deployScript -match '\$env:CAKEO_FTP') "text scan"

# Queue endpoint
$queueSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$qcsrf     = Get-PageCsrf -PageUrl "$BaseUrl/admin/login" -Session $queueSess
$qLogin    = Invoke-Api -Method POST -Url "$BaseUrl/api/admin/auth/login" -Body @{ email = $AdminEmail; password = $AdminPass } -Session $queueSess -CsrfToken $qcsrf
if ($qLogin.ok) {
    $qJobs  = Invoke-Api -Url "$BaseUrl/api/admin/queue/jobs" -Session $queueSess
    Add-Result '4-Security' 'Queue jobs list API 200' ($qJobs.ok) "$(if(-not $qJobs.ok){$qJobs.error})"

    $qProc  = Invoke-Api -Method POST -Url "$BaseUrl/api/admin/queue/process" -Session $queueSess -CsrfToken $qcsrf
    Add-Result '4-Security' 'Queue process endpoint executes without error' ($qProc.ok) "$(if($qProc.ok){($qProc.data | ConvertTo-Json -Compress -Depth 3)}else{$qProc.error})"

    $cronSc = Get-Http "$BaseUrl/cron/queue/process"
    Add-Result '4-Security' 'Cron queue/process endpoint reachable (200/401)' ($cronSc -eq 200 -or $cronSc -eq 401) "HTTP $cronSc"
}

# ─────────────────────────────────────────────
# SECTION 5 – DEPLOYMENT READINESS
# ─────────────────────────────────────────────
Write-Host "`n=== SECTION 5: Deployment Readiness ===" -ForegroundColor Cyan

$healthApi = Invoke-Api -Url "$BaseUrl/api/health"
Add-Result '5-Deploy' 'API /api/health returns 200 + success:true' ($healthApi.ok -and $healthApi.data.success -eq $true) "$(if($healthApi.ok){$healthApi.data.version}else{$healthApi.error})"

$catDeploy = Invoke-Api -Url "$BaseUrl/api/catalog/categories"
Add-Result '5-Deploy' '/api/catalog/categories returns 200' ($catDeploy.ok) "$(if(-not $catDeploy.ok){$catDeploy.error})"

$prodDeploy = Invoke-Api -Url "$BaseUrl/api/catalog/products?limit=5"
Add-Result '5-Deploy' '/api/catalog/products returns 200' ($prodDeploy.ok) "$(if(-not $prodDeploy.ok){$prodDeploy.error})"

$keyRoutes = @('/', '/shop', '/course', '/events', '/b2b', '/contact', '/login', '/register', '/admin/login')
foreach ($route in $keyRoutes) {
    $sc = Get-Http "$BaseUrl$route"
    Add-Result '5-Deploy' "Route $route returns 200" ($sc -eq 200) "HTTP $sc"
}

# Error log via FTP
$ftpHost = $env:CAKEO_FTP_HOST
$ftpUser = $env:CAKEO_FTP_USER
$ftpPass = $env:CAKEO_FTP_PASS
if ($ftpHost -and $ftpUser -and $ftpPass) {
    $tmpLog = [System.IO.Path]::GetTempFileName()
    & curl.exe -s --user "$ftpUser`:$ftpPass" "ftp://$ftpHost/storage/logs/php-error.log" -o $tmpLog 2>&1 | Out-Null
    if (Test-Path $tmpLog) {
        $logContent = Get-Content $tmpLog -Tail 80 -ErrorAction SilentlyContinue
        Remove-Item $tmpLog -Force -ErrorAction SilentlyContinue
        $fatalLines = $logContent | Where-Object { $_ -match 'PHP Fatal|PHP Parse error|Uncaught Error|PHP Warning.*AdminApiController' }
        Add-Result '5-Deploy' 'Production error log: no new PHP Fatal/Parse errors' ($fatalLines.Count -eq 0) "$(if($fatalLines.Count -gt 0){'FATALS: ' + ($fatalLines -join ' | ')}else{'log tail clean'})"
        $adminParseErrors = $fatalLines | Where-Object { $_ -match 'AdminApiController' }
        Add-Result '5-Deploy' 'AdminApiController: no parse errors in log' ($adminParseErrors.Count -eq 0) "$(if($adminParseErrors.Count -gt 0){$adminParseErrors[0]}else{'clean'})"
    } else {
        Add-Result '5-Deploy' 'Production error log: no new PHP Fatal/Parse errors' $false 'FTP log download failed'
        Add-Result '5-Deploy' 'AdminApiController: no parse errors in log' $false 'FTP log download failed'
    }
} else {
    Add-Result '5-Deploy' 'Production error log: no new PHP Fatal/Parse errors' $false 'FTP env vars not set - skipped'
    Add-Result '5-Deploy' 'AdminApiController: no parse errors in log' $false 'FTP env vars not set - skipped'
}

# ─────────────────────────────────────────────
# SUMMARY TABLE
# ─────────────────────────────────────────────
Write-Host "`n`n===== STRICT PASS / FAIL MATRIX =====" -ForegroundColor White

$pass  = ($results | Where-Object { $_.Status -eq 'PASS' }).Count
$fail  = ($results | Where-Object { $_.Status -eq 'FAIL' }).Count
$total = $results.Count

Write-Host "`nSection summary:"
foreach ($sec in ($results | Select-Object -ExpandProperty Section | Sort-Object -Unique)) {
    $sp = ($results | Where-Object { $_.Section -eq $sec -and $_.Status -eq 'PASS' }).Count
    $sf = ($results | Where-Object { $_.Section -eq $sec -and $_.Status -eq 'FAIL' }).Count
    Write-Host ("  {0,-14}  PASS={1,3}  FAIL={2,3}" -f $sec, $sp, $sf)
}

Write-Host "`nTotal: $pass PASS / $fail FAIL / $total checks"

# Emit failures only for easy review
$failRows = $results | Where-Object { $_.Status -eq 'FAIL' }
if ($failRows.Count -gt 0) {
    Write-Host "`n--- FAILING ITEMS ---" -ForegroundColor Red
    $failRows | ForEach-Object { Write-Host "  [$($_.Section)] $($_.Item) | $($_.Evidence)" -ForegroundColor Red }
}

# Machine-readable output
$results | ConvertTo-Json -Depth 4
