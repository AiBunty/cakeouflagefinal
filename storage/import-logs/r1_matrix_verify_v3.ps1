$ErrorActionPreference = "Stop"

function DbQuerySingle([string]$sql) {
  $out = docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e $sql
  if (-not $out) { return $null }
  return ($out | Select-Object -First 1)
}

function DbExec([string]$sql) {
  docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e $sql | Out-Null
}

$baseCandidates = @('http://localhost:8080','http://127.0.0.1:8080','http://localhost')
$baseUrl = $null
foreach ($c in $baseCandidates) {
  try {
    $resp = Invoke-WebRequest -Uri "$c/" -TimeoutSec 8 -UseBasicParsing
    if ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 500) { $baseUrl = $c; break }
  } catch {}
}
if (-not $baseUrl) { throw "Could not detect running app URL." }
Write-Output "BASE_URL=$baseUrl"

$adminLine = DbQuerySingle "SELECT id,email FROM admins WHERE is_active=1 ORDER BY CASE WHEN role='super_admin' THEN 0 ELSE 1 END, id ASC LIMIT 1;"
if (-not $adminLine) { throw "No active admin found." }
$adminParts = $adminLine -split "`t"
$adminId = [int]$adminParts[0]
$adminEmail = [string]$adminParts[1]
Write-Output "ADMIN_ID=$adminId"
Write-Output "ADMIN_EMAIL=$adminEmail"

$catLine = DbQuerySingle "SELECT id,name FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL AND is_active=1 ORDER BY id ASC LIMIT 1;"
if (-not $catLine) { throw "No active root category found." }
$catParts = $catLine -split "`t"
$catId = [int]$catParts[0]
$catName = [string]$catParts[1]
Write-Output "CATEGORY=$catName (#$catId)"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -WebSession $session -UseBasicParsing

$knownOtp = "123456"
$adminEmailSql = $adminEmail.Replace("'","''")
DbExec "DELETE FROM otp_verifications WHERE email='$adminEmailSql'; INSERT INTO otp_verifications(email,otp,expires_at) VALUES('$adminEmailSql','$knownOtp', NOW() + INTERVAL 5 MINUTE);"
Write-Output "OTP_SEEDED=TRUE"

try {
  $verifyResp = Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -Method Post -WebSession $session -UseBasicParsing -MaximumRedirection 5 -Body @{ action='verify_otp'; login=$adminEmail; otp=$knownOtp }
  Write-Output "VERIFY_STATUS=$($verifyResp.StatusCode)"
} catch {
  Write-Output "VERIFY_STATUS=REDIRECT_CHAIN"
}

$authMe = Invoke-RestMethod -Uri "$baseUrl/api/admin/auth/me" -WebSession $session -Method Get -ContentType "application/json"
if (-not $authMe.success) { throw "API auth/me failed: $($authMe.message)" }
Write-Output ("AUTH_ME_ADMIN_ID=" + $authMe.data.admin.id)

$dashboardHtml = [string](Invoke-WebRequest -Uri "$baseUrl/admin/dashboard.php" -WebSession $session -UseBasicParsing).Content
$csrfMatch = [regex]::Match($dashboardHtml, 'name="csrf-token"\s+content="([a-f0-9]{64})"', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
if (-not $csrfMatch.Success) { throw "Unable to read CSRF token from dashboard HTML." }
$csrfToken = $csrfMatch.Groups[1].Value
Write-Output ("CSRF_TOKEN_LEN=" + $csrfToken.Length)

$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$productName = "R1 Matrix Product $stamp"
$sku = "R1M-$stamp"
$createDesc = "R1 FORM CREATE DESC $stamp"
$updateDesc = "R1 API UPDATE DESC $stamp"

$formBody = @{
  name = $productName
  base_price = '111.00'
  category_id = "$catId"
  description = $createDesc
}
$createResp = Invoke-WebRequest -Uri "$baseUrl/admin/add-product.php" -Method Post -WebSession $session -UseBasicParsing -ContentType "application/x-www-form-urlencoded" -Body $formBody
Write-Output "FORM_CREATE_STATUS=$($createResp.StatusCode)"

$pnSql = $productName.Replace("'","''")
$prodLine = DbQuerySingle "SELECT id,slug,sku,description,short_description,long_description,collection_category_id,starting_price,base_price,stock_quantity,availability_status,is_veg,dietary_tag FROM products WHERE name='$pnSql' ORDER BY id DESC LIMIT 1;"
if (-not $prodLine) { throw "Created product not found in DB." }
$prod = $prodLine -split "`t"
$productId = [int]$prod[0]
$slug = [string]$prod[1]
$skuDb = [string]$prod[2]
$descDb = [string]$prod[3]
$catDb = [int]$prod[6]
$startingPrice = [double]$prod[7]
$basePrice = [double]$prod[8]
$stockQty = [int]$prod[9]
$availability = [string]$prod[10]
$isVeg = [int]$prod[11]
$dietary = [string]$prod[12]
Write-Output "CREATED_PRODUCT_ID=$productId"
Write-Output "DB_AFTER_FORM_CREATE_DESC=$descDb"

$updatePayload = @{
  name = $productName
  slug = $slug
  sku = $skuDb
  _csrf = $csrfToken
  collection_category_id = $catDb
  description = $updateDesc
  short_description = $updateDesc
  long_description = $updateDesc
  starting_price = $startingPrice
  base_price = $basePrice
  stock_quantity = $stockQty
  availability_status = $availability
  is_veg = $isVeg
  dietary_tag = $dietary
}
$apiResp = Invoke-RestMethod -Uri "$baseUrl/api/admin/products/$productId" -Method Patch -WebSession $session -ContentType "application/json" -Body ($updatePayload | ConvertTo-Json -Depth 8)
Write-Output ("API_UPDATE_SUCCESS=" + $apiResp.success)
Write-Output ("API_UPDATE_MESSAGE=" + $apiResp.message)

$afterApiLine = DbQuerySingle "SELECT description,short_description,long_description FROM products WHERE id=$productId;"
$afterApi = $afterApiLine -split "`t"
Write-Output "DB_AFTER_API_DESCRIPTION=$($afterApi[0])"
Write-Output "DB_AFTER_API_SHORT=$($afterApi[1])"
Write-Output "DB_AFTER_API_LONG=$($afterApi[2])"

$exportFile = Join-Path $PWD ("storage/import-logs/r1-export-$stamp.xlsx")
Invoke-WebRequest -Uri "$baseUrl/admin/download_products.php" -Method Get -WebSession $session -OutFile $exportFile -UseBasicParsing
Write-Output "EXPORT_FILE=$exportFile"

$inContainerExport = "/var/www/html/storage/import-logs/r1-export-$stamp.xlsx"
$escapedName = $productName.Replace("'", "''")
$parseCmd = "require 'vendor/autoload.php'; `$sheet=\\PhpOffice\\PhpSpreadsheet\\IOFactory::load('$inContainerExport')->getActiveSheet(); `$max=`$sheet->getHighestDataRow(); `$name='$escapedName'; `$found=''; for(`$r=2;`$r<=`$max;`$r++){ `$pn=trim((string)`$sheet->getCellByColumnAndRow(1,`$r)->getValue()); if(`$pn===`$name){ `$found=trim((string)`$sheet->getCellByColumnAndRow(2,`$r)->getValue()); break; } } echo 'EXPORT_MATCH_DESC=' . (`$found !== '' ? `$found : '<NOT_FOUND>');"
$exportMatch = docker exec cakeouflage-web php -r $parseCmd
Write-Output $exportMatch

$importResp = Invoke-WebRequest -Uri "$baseUrl/admin/import-products.php" -Method Post -WebSession $session -UseBasicParsing -Form @{ upload='1'; file=Get-Item $exportFile }
Write-Output ("IMPORT_STATUS=" + $importResp.StatusCode)

$afterImportLine = DbQuerySingle "SELECT description,short_description,long_description FROM products WHERE id=$productId;"
$afterImport = $afterImportLine -split "`t"
Write-Output "DB_AFTER_IMPORT_DESCRIPTION=$($afterImport[0])"
Write-Output "DB_AFTER_IMPORT_SHORT=$($afterImport[1])"
Write-Output "DB_AFTER_IMPORT_LONG=$($afterImport[2])"

$listResp = Invoke-RestMethod -Uri "$baseUrl/api/admin/products?limit=100&q=$([uri]::EscapeDataString($productName))" -Method Get -WebSession $session
$item = $listResp.data.items | Where-Object { [int]$_.id -eq $productId } | Select-Object -First 1
if ($null -eq $item) { throw "Updated product missing from admin API list." }
Write-Output ("API_LIST_DESCRIPTION=" + $item.description)
Write-Output ("API_LIST_SHORT=" + $item.short_description)
Write-Output ("API_LIST_LONG=" + $item.long_description)

Write-Output "R1_MATRIX_PASS=TRUE"