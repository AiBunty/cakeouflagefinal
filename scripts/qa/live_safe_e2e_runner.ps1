Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location (Split-Path -Parent $PSScriptRoot | Split-Path -Parent)

$baseUrl = 'https://cakeouflage.com'
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$evidencePath = Join-Path (Get-Location) "storage/logs/live_safe_e2e_$timestamp.json"
$tmpPhp = Join-Path $env:TEMP "cakeouflage_live_db_helper_$timestamp.php"

@'
<?php
$mode = $argv[1] ?? 'json';
$sql = base64_decode($argv[2] ?? '');
$env = parse_ini_file('.env.production');
$host = $env['DB_HOST'] ?? 'mysql.gb.stackcp.com';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->query($sql);
if ($mode === 'scalar') {
    $value = $stmt->fetchColumn();
    echo json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
'@ | Set-Content -Path $tmpPhp -Encoding UTF8

function Invoke-LiveDbScalar([string]$sql) {
    $enc = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($sql))
    $raw = php $tmpPhp scalar $enc
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return $null
    }
    return ($raw | ConvertFrom-Json)
}

function Invoke-LiveDbRows([string]$sql) {
    $enc = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($sql))
    $raw = php $tmpPhp json $enc
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return @()
    }
    return @($raw | ConvertFrom-Json)
}

function Get-HtmlInputValue([string]$html, [string]$name) {
    $pattern = 'name="' + [regex]::Escape($name) + '"[^>]*value="([^"]*)"'
    $m = [regex]::Match($html, $pattern)
    if ($m.Success) {
        return $m.Groups[1].Value
    }
    return ''
}

function Get-JsStringValue([string]$html, [string]$varName) {
    $pattern = [regex]::Escape($varName) + '\s*=\s*"([^"]+)"'
    $m = [regex]::Match($html, $pattern)
    if ($m.Success) {
        return $m.Groups[1].Value
    }
    return ''
}

function Get-ResponseUri([object]$resp) {
    if ($null -eq $resp) {
        return ''
    }
    if ($resp.PSObject.Properties.Name -contains 'BaseResponse') {
        $base = $resp.BaseResponse
        if ($base -and $base.PSObject.Properties.Name -contains 'ResponseUri' -and $base.ResponseUri) {
            return [string]$base.ResponseUri.AbsoluteUri
        }
        if ($base -and $base.PSObject.Properties.Name -contains 'RequestMessage' -and $base.RequestMessage -and $base.RequestMessage.RequestUri) {
            return [string]$base.RequestMessage.RequestUri.AbsoluteUri
        }
    }
    if ($resp.PSObject.Properties.Name -contains 'Headers') {
        $location = $resp.Headers['Location']
        if ($location) {
            return [string]$location
        }
    }
    return ''
}

try {
    $proofImage = Join-Path (Get-Location) 'public/assets/defaults/default-product-image.png'
    $result = [ordered]@{
        base_url = $baseUrl
        started_at = (Get-Date).ToString('s')
        aliases = @()
        collections = [ordered]@{}
        manual_order = [ordered]@{}
        online_order = [ordered]@{}
        byoc_order = [ordered]@{}
        rollback_ledger = @()
    }

    $adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $adminLogin = 'Dcore'
    $adminEmail = 'aibuntysystems@gmail.com'

    Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -Method Post -WebSession $adminSession -Body @{ login = $adminLogin; action = 'send_otp' } | Out-Null
    Start-Sleep -Milliseconds 500
    $adminOtp = [string](Invoke-LiveDbScalar "SELECT otp FROM otp_verifications WHERE email = '$adminEmail' ORDER BY created_at DESC LIMIT 1")
    if (-not $adminOtp) {
        throw 'Admin OTP not found in live DB.'
    }

    $adminLoginResp = Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -Method Post -WebSession $adminSession -Body @{ login = $adminLogin; otp = $adminOtp; action = 'verify_otp' }
    $adminFinalUrl = Get-ResponseUri $adminLoginResp
    $result.admin_login = [ordered]@{
        success = ($adminFinalUrl -like '*dashboard.php*')
        final_url = $adminFinalUrl
    }

    foreach ($route in @('reports.php','accounting.php','invoices.php','customers.php','telecalling.php','media-center.php','users.php')) {
        $resp = Invoke-WebRequest -Uri "$baseUrl/admin/$route" -WebSession $adminSession
        $aliasFinalUrl = Get-ResponseUri $resp
        $result.aliases += [ordered]@{
            route = $route
            status_code = [int]$resp.StatusCode
            final_url = $aliasFinalUrl
            title = ([regex]::Match($resp.Content, '<title>(.*?)</title>', 'IgnoreCase').Groups[1].Value)
        }
    }

    $manualBeforeCount = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM orders WHERE order_number LIKE 'MAN-%'")
    $manualPage = Invoke-WebRequest -Uri "$baseUrl/admin/manual_order.php" -WebSession $adminSession
    $idempotencyKey = Get-HtmlInputValue $manualPage.Content 'idempotency_key'
    if (-not $idempotencyKey) {
        throw 'Manual order idempotency key missing.'
    }

    $manualResp = Invoke-WebRequest -Uri "$baseUrl/admin/save_manual_order.php" -Method Post -WebSession $adminSession -Body @{
        idempotency_key = $idempotencyKey
        customer_name = 'Parin Daulat'
        customer_email = 'parin11@gmail.com'
        customer_phone = '9330033000'
        item_name = 'Live QA Manual Order'
        amount = '1.00'
        admin_note = 'Live-safe E2E QA manual order'
        fulfilment_mode = 'pickup'
        order_status = 'confirmed'
        payment_status = 'pending'
        payment_method = 'upi_manual'
        order_mode = 'ready_pos'
        matched_user_id = '0'
        order_items = '[]'
    }
    $manualRedirectUrl = Get-ResponseUri $manualResp
    $manualQuery = ''
    if ($manualRedirectUrl -match '\?') {
        $manualQuery = ($manualRedirectUrl -split '\?', 2)[1]
    }
    $manualOrderId = [int]([System.Web.HttpUtility]::ParseQueryString($manualQuery)['order_id'])
    $manualOrder = Invoke-LiveDbRows "SELECT id, order_number, order_status, payment_status, payment_method, grand_total, balance_due_amount, collection_status, created_at FROM orders WHERE id = $manualOrderId LIMIT 1"
    if (-not $manualOrderId -or -not $manualOrder) {
        throw 'Manual order was not created.'
    }

    $result.manual_order = [ordered]@{
        before_count = $manualBeforeCount
        redirect_url = $manualRedirectUrl
        order = $manualOrder[0]
        queue_jobs = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM queue_jobs WHERE payload_json LIKE '%\"recipient\":\"parin11@gmail.com\"%' AND created_at >= (NOW() - INTERVAL 10 MINUTE)")
    }
    $result.rollback_ledger += [ordered]@{
        type = 'manual_order'
        order_id = $manualOrderId
        order_number = $manualOrder[0].order_number
        rollback_note = 'Pending manual QA order. Can be cancelled or marked test by operations if needed.'
    }

    $collectionsPage = Invoke-WebRequest -Uri "$baseUrl/admin/collections_queue.php?payment_status=all&followup_status=all" -WebSession $adminSession
    $collectionCsrf = Get-JsStringValue $collectionsPage.Content 'const collectionCsrf'
    if (-not $collectionCsrf) {
        throw 'Collections CSRF token missing.'
    }

    $followupBefore = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM collection_followup_logs WHERE order_id = $manualOrderId")
    $promiseDate = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
    $collectionsAction = Invoke-RestMethod -Uri "$baseUrl/admin/api/collection-followup-action.php" -Method Post -WebSession $adminSession -Body @{
        _csrf = $collectionCsrf
        order_id = $manualOrderId
        action_type = 'payment_promised'
        note = 'Live-safe QA follow-up proof'
        collection_priority = 'high'
        promise_date = $promiseDate
    }
    $followupRows = Invoke-LiveDbRows "SELECT order_id, action_type, followup_status, message_text, actor_name, created_at FROM collection_followup_logs WHERE order_id = $manualOrderId ORDER BY id DESC LIMIT 3"
    $result.collections = [ordered]@{
        action_response = $collectionsAction
        before_log_count = $followupBefore
        after_log_count = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM collection_followup_logs WHERE order_id = $manualOrderId")
        recent_logs = $followupRows
    }

    $customerSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-RestMethod -Uri "$baseUrl/api/send-otp" -Method Post -WebSession $customerSession -ContentType 'application/json' -Body (@{ email = 'parin11@gmail.com' } | ConvertTo-Json) | Out-Null
    Start-Sleep -Milliseconds 500
    $customerOtp = [string](Invoke-LiveDbScalar "SELECT otp FROM otp_verifications WHERE email = 'parin11@gmail.com' ORDER BY created_at DESC LIMIT 1")
    if (-not $customerOtp) {
        throw 'Customer OTP not found in live DB.'
    }

    $verifyCustomer = Invoke-RestMethod -Uri "$baseUrl/api/verify-otp" -Method Post -WebSession $customerSession -ContentType 'application/json' -Body (@{ email = 'parin11@gmail.com'; otp = $customerOtp; name = 'Parin Daulat'; phone = '9330033000' } | ConvertTo-Json)
    $productRow = (Invoke-LiveDbRows "SELECT p.id AS product_id, pv.id AS variant_id FROM products p INNER JOIN product_variants pv ON pv.product_id = p.id WHERE p.deleted_at IS NULL AND COALESCE(p.availability_status,'active') <> 'out_of_stock' ORDER BY p.id ASC, COALESCE(pv.is_default,0) DESC, pv.id ASC LIMIT 1")[0]
    if (-not $productRow) {
        throw 'No live product/variant available for online order.'
    }

    $cartAdd = Invoke-RestMethod -Uri "$baseUrl/api/cart/items" -Method Post -WebSession $customerSession -ContentType 'application/json' -Body (@{ product_id = [int]$productRow.product_id; variant_id = [int]$productRow.variant_id; quantity = 1 } | ConvertTo-Json)
    $onlineBefore = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM orders WHERE order_number LIKE 'CKF-%'")
    $placeOrder = Invoke-RestMethod -Uri "$baseUrl/api/orders/place" -Method Post -WebSession $customerSession -Form @{
        customer_name = 'Parin Daulat'
        customer_email = 'parin11@gmail.com'
        customer_phone = '9330033000'
        fulfilment_mode = 'pickup'
        payment_method = 'upi_manual'
        payment_proof = Get-Item $proofImage
    }
    $onlineOrderNumber = [string]$placeOrder.data.order_number
    $onlineOrder = Invoke-LiveDbRows "SELECT id, order_number, order_status, payment_status, payment_method, order_mode, order_source, grand_total, payment_proof_url, created_at FROM orders WHERE order_number = '$onlineOrderNumber' LIMIT 1"
    if (-not $onlineOrder) {
        throw 'Online order record not found after API success.'
    }

    $result.online_order = [ordered]@{
        before_count = $onlineBefore
        verify_response = $verifyCustomer
        cart_add_response = $cartAdd
        place_order_response = $placeOrder
        order = $onlineOrder[0]
    }
    $result.rollback_ledger += [ordered]@{
        type = 'online_order'
        order_id = $onlineOrder[0].id
        order_number = $onlineOrder[0].order_number
        rollback_note = 'Pending online QA order with uploaded proof. Leave unpaid or mark internal test if cleanup required.'
    }

    $byocBefore = [int](Invoke-LiveDbScalar "SELECT COUNT(*) FROM orders WHERE order_number LIKE 'BYOC-%'")
    $byocInquiryResp = Invoke-RestMethod -Uri "$baseUrl/api/inquiries/custom-cake" -Method Post -Form @{
        name = 'Parin Daulat'
        email = 'parin11@gmail.com'
        phone_country_code = '+91'
        phone = '9330033000'
        event_information = 'Birthday'
        event_date = ((Get-Date).AddDays(7).ToString('yyyy-MM-dd'))
        number_of_servings_guests = '10'
        budget_range = '1000-1500'
        diet_preference = 'Veg'
        design_breif_notes = 'Live-safe BYOC QA order'
        privacy_consent = '1'
    }
    $inquiry = (Invoke-LiveDbRows "SELECT id, status, created_at FROM inquiries WHERE inquiry_type = 'custom_cake' AND email = 'parin11@gmail.com' AND phone = '9330033000' ORDER BY id DESC LIMIT 1")[0]
    if (-not $inquiry) {
        throw 'BYOC inquiry not found.'
    }

    Invoke-WebRequest -Uri "$baseUrl/admin/build-your-own-cake.php" -Method Post -WebSession $adminSession -Body @{
        action = 'send_quote'
        inquiry_id = [string]$inquiry.id
        quote_subject = 'Live Safe BYOC Quote'
        quote_message = 'QA quote for live-safe end-to-end proof.'
        quote_amount = '2.00'
        expiry_hours = '72'
    } | Out-Null

    $quoteRow = (Invoke-LiveDbRows "SELECT q.id, q.quote_number, q.status, l.token FROM byoc_quotes q INNER JOIN byoc_quote_links l ON l.byoc_quote_id = q.id WHERE q.inquiry_id = $($inquiry.id) ORDER BY q.id DESC LIMIT 1")[0]
    if (-not $quoteRow) {
        throw 'BYOC quote not created.'
    }

    $byocAccept = Invoke-RestMethod -Uri "$baseUrl/api/inquiries/custom-cake/quote-accept/$($quoteRow.token)" -Method Post -Form @{
        fulfillment_type = 'pickup'
        payment_type = 'full'
        advance_amount = '0'
    }
    $byocOrderNumber = [string]$byocAccept.data.order_number
    $byocOrder = Invoke-LiveDbRows "SELECT id, order_number, order_status, payment_status, order_source, byoc_quote_id, grand_total, created_at FROM orders WHERE order_number = '$byocOrderNumber' LIMIT 1"
    if (-not $byocOrder) {
        throw 'BYOC order record not found after acceptance.'
    }

    $result.byoc_order = [ordered]@{
        before_count = $byocBefore
        inquiry_response = $byocInquiryResp
        inquiry = $inquiry
        quote = $quoteRow
        accept_response = $byocAccept
        order = $byocOrder[0]
    }
    $result.rollback_ledger += [ordered]@{
        type = 'byoc_order'
        order_id = $byocOrder[0].id
        order_number = $byocOrder[0].order_number
        rollback_note = 'Pending BYOC QA order created from accepted quote. Leave unpaid or annotate internally if cleanup required.'
    }

    $result.finished_at = (Get-Date).ToString('s')
    $result | ConvertTo-Json -Depth 8 | Set-Content -Path $evidencePath -Encoding UTF8
    Write-Output "EVIDENCE_PATH=$evidencePath"
} finally {
    Remove-Item $tmpPhp -Force -ErrorAction SilentlyContinue
}
