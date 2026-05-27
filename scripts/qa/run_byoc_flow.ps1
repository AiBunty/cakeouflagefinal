param(
  [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"

function Get-Csrf {
  param([string]$Url, [Microsoft.PowerShell.Commands.WebRequestSession]$Session)
  $resp = Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing
  $m = [regex]::Match($resp.Content, 'name="csrf-token" content="([^"]+)"')
  if (-not $m.Success) { throw "CSRF token not found at $Url" }
  return $m.Groups[1].Value
}

function Get-OrderNumberById {
  param([int]$OrderId)
  $query = "SELECT order_number FROM orders WHERE id=$OrderId LIMIT 1;"
  $value = (docker exec cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local -e $query).Trim()
  if (-not $value) { throw "Unable to find order_number for order_id=$OrderId" }
  return $value
}

$stamp = Get-Date -Format "yyyyMMddHHmmss"
$token = "qa-byoc-" + [guid]::NewGuid().ToString('N').Substring(0, 20)
$quoteNumber = "BYQ-$stamp"
$email = "byoc.qa.$stamp@example.com"
$eventDate = (Get-Date).AddDays(3).ToString('yyyy-MM-dd')
$messageJson = '{"event_date":"' + $eventDate + '","event_information":"Birthday","design_breif_notes":"QA BYOC acceptance flow"}'

$seedSql = @"
INSERT INTO inquiries (inquiry_type, name, email, phone, message, status)
VALUES ('custom_cake','BYOC QA Customer','$email','9876543210','$messageJson','new');
SET @inq_id = LAST_INSERT_ID();
INSERT INTO byoc_quotes (inquiry_id, quote_number, quote_subject, quote_message, quote_amount, currency, status, expires_at)
VALUES (@inq_id, '$quoteNumber', 'QA BYOC Cake Quote', 'QA BYOC Quote Message', 1299.00, 'INR', 'sent', DATE_ADD(NOW(), INTERVAL 2 DAY));
SET @quote_id = LAST_INSERT_ID();
INSERT INTO byoc_quote_links (byoc_quote_id, token, expires_at, is_active)
VALUES (@quote_id, '$token', DATE_ADD(NOW(), INTERVAL 2 DAY), 1);
SELECT @inq_id AS inquiry_id, @quote_id AS quote_id;
"@

$seedOut = $seedSql | docker exec -i cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local

$publicSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$publicCsrf = Get-Csrf -Url "$BaseUrl/custom-cake-inquiry" -Session $publicSession

$acceptBody = @{
  fulfillment_type = 'delivery'
  delivery_street = 'QA BYOC Street 44'
  delivery_pincode = '560001'
  delivery_maps_link = 'https://maps.example/byoc-qa'
  _csrf = $publicCsrf
} | ConvertTo-Json -Compress

$acceptResp = Invoke-WebRequest -Uri "$BaseUrl/api/inquiries/custom-cake/quote-accept/$token" -Method Post -WebSession $publicSession -Headers @{ 'X-CSRF-Token' = $publicCsrf } -ContentType 'application/json' -Body $acceptBody -UseBasicParsing
if ($acceptResp.StatusCode -ne 201 -and $acceptResp.StatusCode -ne 200) {
  throw "BYOC quote accept failed: HTTP $($acceptResp.StatusCode) $($acceptResp.Content)"
}
$acceptObj = $acceptResp.Content | ConvertFrom-Json
$orderId = [int]($acceptObj.data.order_id)
if ($orderId -le 0) { throw "BYOC acceptance did not return valid order_id" }
$orderNumber = Get-OrderNumberById -OrderId $orderId

$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminCsrf = Get-Csrf -Url "$BaseUrl/category" -Session $adminSession
$adminLoginBody = @{ email='cakeouflage@gmail.com'; password='admin123'; _csrf=$adminCsrf } | ConvertTo-Json -Compress
$adminLoginResp = Invoke-WebRequest -Uri "$BaseUrl/api/admin/auth/login" -Method Post -WebSession $adminSession -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $adminCsrf } -Body $adminLoginBody -UseBasicParsing
if ($adminLoginResp.StatusCode -ne 200) { throw "Admin API login failed: $($adminLoginResp.Content)" }

$headers = @{ 'Content-Type' = 'application/json'; 'X-CSRF-Token' = $adminCsrf }

$confirmPaymentBody = @{ payment_method = 'upi_manual'; _csrf = $adminCsrf } | ConvertTo-Json -Compress
$confirmPaymentResp = Invoke-WebRequest -Uri "$BaseUrl/api/admin/orders/$orderId/confirm-payment" -Method Post -WebSession $adminSession -Headers $headers -Body $confirmPaymentBody -SkipHttpErrorCheck

$preparingBody = @{ order_status = 'preparing'; _csrf = $adminCsrf } | ConvertTo-Json -Compress
$preparingResp = Invoke-WebRequest -Uri "$BaseUrl/api/admin/orders/$orderId/status" -Method Patch -WebSession $adminSession -Headers $headers -Body $preparingBody -SkipHttpErrorCheck

$deliveredBody = @{ order_status = 'delivered'; _csrf = $adminCsrf } | ConvertTo-Json -Compress
$deliveredResp = Invoke-WebRequest -Uri "$BaseUrl/api/admin/orders/$orderId/status" -Method Patch -WebSession $adminSession -Headers $headers -Body $deliveredBody -SkipHttpErrorCheck

$result = [ordered]@{
  flow = 'byoc'
  token = $token
  order_id = $orderId
  order_number = $orderNumber
  quote_accept_status = [int]$acceptResp.StatusCode
  confirm_payment_status = [int]$confirmPaymentResp.StatusCode
  preparing_status = [int]$preparingResp.StatusCode
  delivered_status = [int]$deliveredResp.StatusCode
  quote_accept_response = $acceptObj
  confirm_payment_response = ($confirmPaymentResp.Content | ConvertFrom-Json)
  delivered_response = ($deliveredResp.Content | ConvertFrom-Json)
}

$resultJson = $result | ConvertTo-Json -Depth 8
$resultPath = "./storage/logs/qa_byoc_result.json"
$resultJson | Out-File -FilePath $resultPath -Encoding utf8
Write-Output $resultJson
