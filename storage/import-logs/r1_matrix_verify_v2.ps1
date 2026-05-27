$ErrorActionPreference = "Stop"

function Invoke-DbSelectJson([string]$sql) {
  $b64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($sql))
  $php = "require 'admin/includes/db.php'; `$sql=base64_decode('$b64'); `$r=`$conn->query(`$sql); if(!`$r){fwrite(STDERR,`$conn->error); exit(2);} while(`$row=`$r->fetch_assoc()){echo json_encode(`$row, JSON_UNESCAPED_SLASHES), PHP_EOL;}"
  php -r $php
}

function Invoke-DbExec([string]$sql) {
  $b64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($sql))
  $php = "require 'admin/includes/db.php'; `$sql=base64_decode('$b64'); if(!`$conn->multi_query(`$sql)){fwrite(STDERR,`$conn->error); exit(2);} do{}while(`$conn->more_results() && `$conn->next_result()); echo 'OK';"
  php -r $php | Out-Null
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

$adminRow = (Invoke-DbSelectJson "SELECT id,email,full_name FROM admins WHERE is_active=1 ORDER BY CASE WHEN role='super_admin' THEN 0 ELSE 1 END, id ASC LIMIT 1" | Select-Object -First 1)
if (-not $adminRow) { throw "No active admin found." }
$admin = $adminRow | ConvertFrom-Json
$adminEmail = [string]$admin.email
Write-Output "ADMIN_EMAIL=$adminEmail"

$catRow = (Invoke-DbSelectJson "SELECT id,name FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL AND is_active=1 ORDER BY id ASC LIMIT 1" | Select-Object -First 1)
if (-not $catRow) { throw "No active root category found." }
$cat = $catRow | ConvertFrom-Json
$catId = [int]$cat.id
Write-Output "CATEGORY=$($cat.name) (#$catId)"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -WebSession $session -UseBasicParsing

$knownOtp = "123456"
$adminEmailSql = $adminEmail.Replace("'","''")
Invoke-DbExec "DELETE FROM otp_verifications WHERE email='$adminEmailSql'; INSERT INTO otp_verifications(email,otp,expires_at) VALUES('$adminEmailSql','$knownOtp', NOW() + INTERVAL 5 MINUTE)"
Write-Output "OTP_SEEDED=TRUE"

$verifyResp = Invoke-WebRequest -Uri "$baseUrl/admin/login.php" -Method Post -WebSession $session -UseBasicParsing -MaximumRedirection 0 -SkipHttpErrorCheck -Body @{ action='verify_otp'; login=$adminEmail; otp=$knownOtp }
Write-Output "VERIFY_STATUS=$($verifyResp.StatusCode)"

$authMe = Invoke-RestMethod -Uri "$baseUrl/api/admin/auth/me" -WebSession $session -Method Get -ContentType "application/json"
if (-not $authMe.success) { throw "API auth/me failed: $($authMe.message)" }
Write-Output ("AUTH_ME_ADMIN_ID=" + $authMe.data.admin.id)

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
  dietary_tag = 'regular'
  is_veg = '1'
  'variant_name[]' = '1 lb'
  'variant_price[]' = '111.00'
  'variant_unit_type[]' = 'size'
  'variant_sku[]' = $sku
  variant_default = '0'
}
$createResp = Invoke-WebRequest -Uri "$baseUrl/admin/add-product.php" -Method Post -WebSession $session -UseBasicParsing -MaximumRedirection 0 -SkipHttpErrorCheck -Body $formBody
Write-Output "FORM_CREATE_STATUS=$($createResp.StatusCode)"

$pnSql = $productName.Replace("'","''")
$prodRowRaw = (Invoke-DbSelectJson "SELECT id,name,slug,sku,description,short_description,long_description,collection_category_id,starting_price,base_price,stock_quantity,availability_status,is_veg,dietary_tag FROM products WHERE name='$pnSql' ORDER BY id DESC LIMIT 1" | Select-Object -First 1)
if (-not $prodRowRaw) { throw "Created product not found in DB." }
$prod = $prodRowRaw | ConvertFrom-Json
$productId = [int]$prod.id
Write-Output "CREATED_PRODUCT_ID=$productId"
Write-Output ("DB_AFTER_FORM_CREATE_DESC=" + $prod.description)

$updatePayload = @{
  name = $prod.name
  slug = $prod.slug
  sku = $prod.sku
  collection_category_id = [int]$prod.collection_category_id
  description = $updateDesc
  short_description = $updateDesc.Substring(0, [Math]::Min(250, $updateDesc.Length))
  long_description = $updateDesc
  starting_price = [double]$prod.starting_price
  base_price = [double]$prod.base_price
  stock_quantity = [int]$prod.stock_quantity
  availability_status = [string]$prod.availability_status
  is_veg = [int]$prod.is_veg
  dietary_tag = [string]$prod.dietary_tag
}
$updateJson = $updatePayload | ConvertTo-Json -Depth 8
$apiResp = Invoke-RestMethod -Uri "$baseUrl/api/admin/products/$productId" -Method Patch -WebSession $session -ContentType "application/json" -Body $updateJson
Write-Output ("API_UPDATE_SUCCESS=" + $apiResp.success)
Write-Output ("API_UPDATE_MESSAGE=" + $apiResp.message)

$afterApiRaw = (Invoke-DbSelectJson "SELECT id,description,short_description,long_description FROM products WHERE id=$productId" | Select-Object -First 1)
$afterApi = $afterApiRaw | ConvertFrom-Json
Write-Output ("DB_AFTER_API_DESCRIPTION=" + $afterApi.description)
Write-Output ("DB_AFTER_API_SHORT=" + $afterApi.short_description)
Write-Output ("DB_AFTER_API_LONG=" + $afterApi.long_description)

$exportFile = Join-Path $PWD ("storage/import-logs/r1-export-$stamp.xlsx")
Invoke-WebRequest -Uri "$baseUrl/admin/download_products.php" -Method Get -WebSession $session -OutFile $exportFile -UseBasicParsing
Write-Output "EXPORT_FILE=$exportFile"

$parseCmd = @"
require 'vendor/autoload.php';
`$file = '$($exportFile.Replace('\\','\\\\'))';
`$name = '$($productName.Replace("'","''"))';
`$sheet = \\PhpOffice\\PhpSpreadsheet\\IOFactory::load(`$file)->getActiveSheet();
`$max = `$sheet->getHighestDataRow();
`$found = false;
for (`$r=2; `$r <= `$max; `$r++) {
  `$pn = trim((string)`$sheet->getCellByColumnAndRow(1, `$r)->getValue());
  if (`$pn === `$name) {
    `$desc = trim((string)`$sheet->getCellByColumnAndRow(2, `$r)->getValue());
    echo "EXPORT_MATCH_DESC=`$desc" . PHP_EOL;
    `$found = true;
    break;
  }
}
if (!`$found) { echo "EXPORT_MATCH_DESC=<NOT_FOUND>" . PHP_EOL; }
"@
php -r $parseCmd

$importResp = Invoke-WebRequest -Uri "$baseUrl/admin/import-products.php" -Method Post -WebSession $session -UseBasicParsing -Form @{ upload='1'; file=Get-Item $exportFile }
Write-Output ("IMPORT_STATUS=" + $importResp.StatusCode)

$afterImportRaw = (Invoke-DbSelectJson "SELECT id,description,short_description,long_description FROM products WHERE id=$productId" | Select-Object -First 1)
$afterImport = $afterImportRaw | ConvertFrom-Json
Write-Output ("DB_AFTER_IMPORT_DESCRIPTION=" + $afterImport.description)
Write-Output ("DB_AFTER_IMPORT_SHORT=" + $afterImport.short_description)
Write-Output ("DB_AFTER_IMPORT_LONG=" + $afterImport.long_description)

$listResp = Invoke-RestMethod -Uri "$baseUrl/api/admin/products?limit=100&q=$([uri]::EscapeDataString($productName))" -Method Get -WebSession $session
$item = $listResp.data.items | Where-Object { $_.id -eq $productId } | Select-Object -First 1
if ($null -eq $item) { throw "Updated product missing from admin API list." }
Write-Output ("API_LIST_DESCRIPTION=" + $item.description)
Write-Output ("API_LIST_SHORT=" + $item.short_description)
Write-Output ("API_LIST_LONG=" + $item.long_description)

Write-Output "R1_MATRIX_PASS=TRUE"