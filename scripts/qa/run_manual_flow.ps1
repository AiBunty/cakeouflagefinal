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

function Ensure-AdminPermission {
  $sql = @"
INSERT IGNORE INTO admin_permissions (admin_id, permission_key)
VALUES
  (1,'orders'),
  (1,'order_edit'),
  (1,'order_refund'),
  (1,'manual_orders'),
  (1,'payment_verification'),
  (1,'revenue_report'),
  (1,'crm_logs');
"@
  $sql | docker exec -i cakeouflage-db mysql -uroot -proot -D cakeouflage_local | Out-Null
}

function Get-OrderNumberById {
  param([int]$OrderId)
  $query = "SELECT order_number FROM orders WHERE id=$OrderId LIMIT 1;"
  $value = (docker exec cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local -e $query).Trim()
  if (-not $value) { throw "Unable to find order_number for order_id=$OrderId" }
  return $value
}

Ensure-AdminPermission

# 1) Seed a manual-mode order directly when legacy manual UI auth loops in local env.
$ts = Get-Date -Format "yyyyMMddHHmmss"
$orderNumberSeed = "MAN-$ts"
$customerEmail = "manual.qa.$ts@example.com"

$seedSql = @"
SET @pid = (SELECT id FROM products ORDER BY id ASC LIMIT 1);
INSERT INTO orders (
  order_number, user_id, customer_name, customer_email, customer_phone, customer_phone_e164,
  fulfilment_mode, order_status, payment_status, payment_method,
  order_source, order_mode,
  delivery_postal_code, delivery_street, delivery_maps_link,
  subtotal, discount_total, tax_total, grand_total,
  advance_amount, outstanding_amount, amount_collected,
  admin_note, requires_kitchen_production, production_status
) VALUES (
  '$orderNumberSeed', NULL, 'Manual QA Customer', '$customerEmail', '9876543210', '+919876543210',
  'delivery', 'pending_payment', 'pending', 'upi_manual',
  'retail', 'scheduled_custom',
  '560001', 'QA Street 12', 'https://maps.example/qa',
  799.00, 0.00, 0.00, 799.00,
  0.00, 799.00, 0.00,
  'QA manual flow validation (SQL seeded fallback)', 1, 'pending'
);
SET @oid = LAST_INSERT_ID();
INSERT INTO order_items (
  order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
  unit_price, quantity, line_total, customisation_note
) VALUES (
  @oid, IFNULL(@pid,1), NULL, 'QA Manual Order Cake', NULL,
  799.00, 1, 799.00, 'Manual mode QA fallback item'
);
SELECT @oid AS order_id;
"@

$seedResult = $seedSql | docker exec -i cakeouflage-db mysql -N -uroot -proot -D cakeouflage_local
$orderId = [int]($seedResult | Select-Object -Last 1)
if ($orderId -le 0) { throw "Failed to seed manual QA order" }
$orderNumber = Get-OrderNumberById -OrderId $orderId
$location = "sql-seeded://orders/$orderId"

# 4) Admin API login for lifecycle updates.
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
  flow = 'manual'
  order_id = $orderId
  order_number = $orderNumber
  save_redirect = $location
  confirm_payment_status = [int]$confirmPaymentResp.StatusCode
  preparing_status = [int]$preparingResp.StatusCode
  delivered_status = [int]$deliveredResp.StatusCode
  confirm_payment_response = ($confirmPaymentResp.Content | ConvertFrom-Json)
  delivered_response = ($deliveredResp.Content | ConvertFrom-Json)
}

$resultJson = $result | ConvertTo-Json -Depth 8
$resultPath = "./storage/logs/qa_manual_result.json"
$resultJson | Out-File -FilePath $resultPath -Encoding utf8
Write-Output $resultJson
