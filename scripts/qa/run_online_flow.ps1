Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"
$ErrorActionPreference = 'Stop'

function Get-Csrf {
  param(
    [string]$Url,
    [Microsoft.PowerShell.Commands.WebRequestSession]$Session
  )
  $page = Invoke-WebRequest -Uri $Url -WebSession $Session -SkipHttpErrorCheck
  $m = [regex]::Match($page.Content, 'csrf-token" content="([a-f0-9]{64})"')
  if ($m.Success) { return $m.Groups[1].Value }
  throw "CSRF token not found at $Url"
}

# Ensure online coupon exists
$couponSql = @"
INSERT INTO coupons (code, discount_type, discount_value, max_discount, min_order_amount, usage_limit, usage_count, starts_at, ends_at, target_mode, auto_apply, applicable_to, is_active, is_deleted, created_at, updated_at)
VALUES ('QAONLINE10','percentage',10,250,100,9999,0,NOW() - INTERVAL 1 DAY,NOW() + INTERVAL 30 DAY,'all_users',0,'online',1,0,NOW(),NOW())
ON DUPLICATE KEY UPDATE is_active=1, applicable_to='online', starts_at=NOW() - INTERVAL 1 DAY, ends_at=NOW() + INTERVAL 30 DAY;
"@
docker exec cakeouflage-db mysql -uroot -proot -D cakeouflage_local -e $couponSql | Out-Null

$deliverySeedSql = @"
INSERT INTO delivery_pincodes (postal_code, area_name, approx_distance_km, is_serviceable, requires_manual_approval, created_at, updated_at)
VALUES ('422001', 'Nashik QA Zone', 5.00, 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE area_name = VALUES(area_name), approx_distance_km = VALUES(approx_distance_km), is_serviceable = 1, requires_manual_approval = 0;

INSERT INTO delivery_distance_slabs (slab_label, min_km, max_km, delivery_fee, is_available, created_at, updated_at)
SELECT 'QA Standard Radius', 0.00, 15.00, 60.00, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM delivery_distance_slabs WHERE min_km = 0.00 AND max_km = 15.00 LIMIT 1
);
"@
docker exec cakeouflage-db mysql -uroot -proot -D cakeouflage_local -e $deliverySeedSql | Out-Null

$custSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = Get-Csrf -Url "http://localhost:8080/login" -Session $custSession

$loginBody = @{ email='customer@cakeouflage.com'; password='admin123'; _csrf=$csrf } | ConvertTo-Json -Compress
$loginResp = Invoke-WebRequest -Uri "http://localhost:8080/api/auth/login" -Method Post -WebSession $custSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $loginBody -SkipHttpErrorCheck
if ($loginResp.StatusCode -ne 200) { throw "Customer login failed: $($loginResp.Content)" }

# Ensure cart exists for this authenticated session
$null = Invoke-WebRequest -Uri "http://localhost:8080/api/cart" -WebSession $custSession -SkipHttpErrorCheck

$productsResp = Invoke-WebRequest -Uri "http://localhost:8080/api/catalog/products?limit=1" -WebSession $custSession -SkipHttpErrorCheck
$productsObj = $productsResp.Content | ConvertFrom-Json
$productId = [int]$productsObj.data.items[0].id
if ($productId -le 0) { throw "No product found" }

$toppersResp = Invoke-WebRequest -Uri "http://localhost:8080/api/toppers" -WebSession $custSession -SkipHttpErrorCheck
$toppersObj = $toppersResp.Content | ConvertFrom-Json
$topperId = 0
if ($toppersObj.success -and $toppersObj.data.Count -gt 0) {
  $topperId = [int]$toppersObj.data[0].id
}

$addBody = @{ product_id=$productId; quantity=1; cake_message='QA ONLINE FLOW'; topper_id=$topperId; _csrf=$csrf } | ConvertTo-Json -Compress
$addResp = Invoke-WebRequest -Uri "http://localhost:8080/api/cart/items" -Method Post -WebSession $custSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $addBody -SkipHttpErrorCheck

$couponBody = @{ code='QAONLINE10'; _csrf=$csrf } | ConvertTo-Json -Compress
$couponResp = Invoke-WebRequest -Uri "http://localhost:8080/api/cart/coupon" -Method Post -WebSession $custSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $couponBody -SkipHttpErrorCheck

$tomorrow = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
$slotResp = Invoke-WebRequest -Uri ("http://localhost:8080/api/fulfilment/slots?mode=delivery&date=" + $tomorrow) -WebSession $custSession -SkipHttpErrorCheck
$slotObj = $slotResp.Content | ConvertFrom-Json
$slotId = [int]$slotObj.data.items[0].id
if ($slotId -le 0) { throw "No delivery slot found" }

$postalLookupSql = "SELECT postal_code FROM delivery_pincodes WHERE is_serviceable = 1 ORDER BY postal_code ASC LIMIT 1;"
$postalCodeRaw = docker exec cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local -e $postalLookupSql
$postalCode = [string]($postalCodeRaw | Select-Object -First 1)
if ($null -ne $postalCode) {
  $postalCode = $postalCode.Trim()
}
if ([string]::IsNullOrWhiteSpace($postalCode)) {
  $postalCode = '422001'
}

$previewBody = @{ fulfilment_mode='delivery'; postal_code=$postalCode; _csrf=$csrf } | ConvertTo-Json -Compress
$previewResp = Invoke-WebRequest -Uri "http://localhost:8080/api/checkout/preview" -Method Post -WebSession $custSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $previewBody -SkipHttpErrorCheck

$proofPath = "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce\public\uploads\1776328295_hamper1.jpg"
if (!(Test-Path $proofPath)) { throw "Proof image missing: $proofPath" }

$form = @{
  customer_name='Parin Daulat'
  customer_email='parin11@gmail.com'
  customer_phone='+919330033000'
  fulfilment_mode='delivery'
  payment_method='upi_manual'
  delivery_pincode=$postalCode
  delivery_street='QA Street 1, Nashik'
  delivery_maps_link='https://maps.google.com/?q=19.9975,73.7898'
  delivery_date=$tomorrow
  slot_id=$slotId
  _csrf=$csrf
  payment_proof=(Get-Item $proofPath)
}
$placeResp = Invoke-WebRequest -Uri "http://localhost:8080/api/orders/place" -Method Post -WebSession $custSession -Headers @{ 'X-CSRF-Token'=$csrf } -Form $form -SkipHttpErrorCheck
$placeObj = $placeResp.Content | ConvertFrom-Json
if (-not $placeObj.success) { throw "Place order failed: $($placeResp.Content)" }
$orderNumber = [string]$placeObj.data.order_number

$orderLookupSql = "SELECT id FROM orders WHERE order_number='${orderNumber}' LIMIT 1;"
$orderIdRaw = docker exec cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local -e $orderLookupSql
$orderId = [int]($orderIdRaw | Select-Object -First 1)
if ($orderId -le 0) { throw "Order lookup failed for $orderNumber" }

$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminCsrf = Get-Csrf -Url "http://localhost:8080/category" -Session $adminSession
$adminLoginBody = @{ email='cakeouflage@gmail.com'; password='admin123'; _csrf=$adminCsrf } | ConvertTo-Json -Compress
$adminLoginResp = Invoke-WebRequest -Uri "http://localhost:8080/api/admin/auth/login" -Method Post -WebSession $adminSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$adminCsrf } -Body $adminLoginBody -SkipHttpErrorCheck
if ($adminLoginResp.StatusCode -ne 200) { throw "Admin login failed: $($adminLoginResp.Content)" }

$confirmBody = @{ payment_method='upi_manual'; _csrf=$adminCsrf } | ConvertTo-Json -Compress
$confirmResp = Invoke-WebRequest -Uri "http://localhost:8080/api/admin/orders/$orderId/confirm-payment" -Method Post -WebSession $adminSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$adminCsrf } -Body $confirmBody -SkipHttpErrorCheck

$prepBody = @{ order_status='preparing'; _csrf=$adminCsrf } | ConvertTo-Json -Compress
$prepResp = Invoke-WebRequest -Uri "http://localhost:8080/api/admin/orders/$orderId/status" -Method Patch -WebSession $adminSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$adminCsrf } -Body $prepBody -SkipHttpErrorCheck

$delBody = @{ order_status='delivered'; _csrf=$adminCsrf } | ConvertTo-Json -Compress
$delResp = Invoke-WebRequest -Uri "http://localhost:8080/api/admin/orders/$orderId/status" -Method Patch -WebSession $adminSession -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$adminCsrf } -Body $delBody -SkipHttpErrorCheck

$result = [ordered]@{
  flow='online'
  order_number=$orderNumber
  order_id=$orderId
  customer_login_status=$loginResp.StatusCode
  add_item_status=$addResp.StatusCode
  coupon_status=$couponResp.StatusCode
  preview_status=$previewResp.StatusCode
  place_status=$placeResp.StatusCode
  confirm_payment_status=$confirmResp.StatusCode
  preparing_status=$prepResp.StatusCode
  delivered_status=$delResp.StatusCode
  confirm_payment_response=($confirmResp.Content | ConvertFrom-Json)
  delivered_response=($delResp.Content | ConvertFrom-Json)
}

if (!(Test-Path "storage/logs")) { New-Item -ItemType Directory -Path "storage/logs" | Out-Null }
$result | ConvertTo-Json -Depth 8 | Out-File -FilePath "storage/logs/qa_online_result.json" -Encoding utf8
Write-Output ($result | ConvertTo-Json -Depth 6)
