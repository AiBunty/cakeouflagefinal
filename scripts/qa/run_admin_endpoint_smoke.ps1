param(
  [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"
Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$page = Invoke-WebRequest -Uri "$BaseUrl/category" -WebSession $session -SkipHttpErrorCheck
$csrf = [regex]::Match($page.Content, 'csrf-token" content="([a-f0-9]{64})"').Groups[1].Value
if (-not $csrf) { throw "CSRF token not found" }

$loginBody = @{ email='cakeouflage@gmail.com'; password='admin123'; _csrf=$csrf } | ConvertTo-Json -Compress
$login = Invoke-WebRequest -Uri "$BaseUrl/api/admin/auth/login" -Method Post -WebSession $session -Headers @{ 'Content-Type'='application/json'; 'X-CSRF-Token'=$csrf } -Body $loginBody -SkipHttpErrorCheck
if ($login.StatusCode -ne 200) { throw "Admin login failed: $($login.Content)" }

$endpoints = @(
  '/api/admin/dashboard/summary',
  '/api/admin/finance/summary',
  '/api/admin/reports/summary',
  '/api/admin/orders?limit=5',
  '/api/admin/refunds'
)

$rows = foreach ($endpoint in $endpoints) {
  $response = Invoke-WebRequest -Uri ($BaseUrl + $endpoint) -WebSession $session -Headers @{ 'X-CSRF-Token' = $csrf } -SkipHttpErrorCheck
  [ordered]@{
    endpoint = $endpoint
    status = [int]$response.StatusCode
    body_head = $response.Content.Substring(0, [Math]::Min(220, $response.Content.Length))
  }
}

$rows | ConvertTo-Json -Depth 6 | Out-File -FilePath "storage/logs/qa_admin_endpoints.json" -Encoding utf8
$rows | ConvertTo-Json -Depth 6
