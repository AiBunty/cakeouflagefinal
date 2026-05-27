param(
  [int]$OrderId = 6,
  [double]$RefundAmount = 100.00,
  [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"

Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"

$sql = @"
INSERT IGNORE INTO admin_permissions (admin_id, permission_key) VALUES
(1,'order_refund'),(1,'can_approve_refund'),(1,'can_force_refund'),(1,'can_view_refund_reports');
"@
$sql | docker exec -i cakeouflage-db mysql -uroot -proot -D cakeouflage_local | Out-Null

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$page = Invoke-WebRequest -Uri "$BaseUrl/category" -WebSession $session -SkipHttpErrorCheck
$csrf = [regex]::Match($page.Content, 'csrf-token" content="([a-f0-9]{64})"').Groups[1].Value
if (-not $csrf) { throw "Failed to get CSRF token" }

$loginBody = @{ email='cakeouflage@gmail.com'; password='admin123'; _csrf=$csrf } | ConvertTo-Json -Compress
$login = Invoke-WebRequest -Uri "$BaseUrl/api/admin/auth/login" -Method Post -WebSession $session -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $loginBody -SkipHttpErrorCheck
if ($login.StatusCode -ne 200) { throw "Admin login failed: $($login.Content)" }

$requestBody = @{ reason_code='OTHER'; reason_notes='QA refund request flow'; refund_amount=$RefundAmount; _csrf=$csrf } | ConvertTo-Json -Compress
$req = Invoke-WebRequest -Uri "$BaseUrl/api/admin/orders/$OrderId/refund/process" -Method Post -WebSession $session -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $requestBody -SkipHttpErrorCheck
$reqObj = $req.Content | ConvertFrom-Json
if (-not $reqObj.success) { throw "Refund request failed: $($req.Content)" }
$refundId = [int]$reqObj.refund_id
if ($refundId -le 0) { throw "Refund request did not return refund_id" }

$approveBody = @{ approved_amount=$RefundAmount; _csrf=$csrf } | ConvertTo-Json -Compress
$app = Invoke-WebRequest -Uri "$BaseUrl/api/admin/refunds/$refundId/approve" -Method Post -WebSession $session -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $approveBody -SkipHttpErrorCheck
$appObj = $app.Content | ConvertFrom-Json

$result = [ordered]@{
  order_id = $OrderId
  refund_amount = $RefundAmount
  refund_request_status = [int]$req.StatusCode
  refund_id = $refundId
  refund_approve_status = [int]$app.StatusCode
  request_response = $reqObj
  approve_response = $appObj
}

$result | ConvertTo-Json -Depth 8 | Out-File -FilePath "storage/logs/qa_refund_result.json" -Encoding utf8
Write-Output ($result | ConvertTo-Json -Depth 8)
