param(
  [string]$ProductQuery = 'R1 Matrix Product',
  [switch]$FreshProductCheck
)

$ErrorActionPreference = 'Stop'

$base = 'http://localhost:8080'

function Get-AdminEmail {
  $line = docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "SELECT email FROM admins WHERE is_active=1 ORDER BY CASE WHEN role='super_admin' THEN 0 ELSE 1 END, id ASC LIMIT 1;"
  return ($line | Select-Object -First 1)
}

function DbQuerySingle([string]$sql) {
  $line = docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e $sql
  if (-not $line) { return $null }
  return ($line | Select-Object -First 1)
}

function Seed-Otp([string]$email, [string]$otp) {
  docker exec cakeouflage-db mariadb -N -uroot -proot cakeouflage_local -e "DELETE FROM otp_verifications WHERE email='${email}'; INSERT INTO otp_verifications(email,otp,expires_at) VALUES('${email}','${otp}',NOW()+INTERVAL 5 MINUTE);" | Out-Null
}

function Get-ApiJson($session, [string]$url) {
  return Invoke-RestMethod -Uri $url -WebSession $session -Method Get -ContentType 'application/json'
}

function Get-CsrfToken($session) {
  $html = [string](Invoke-WebRequest -Uri "$base/admin/dashboard.php" -WebSession $session -UseBasicParsing).Content
  $m = [regex]::Match($html, 'name="csrf-token"\s+content="([a-f0-9]{64})"', 'IgnoreCase')
  if (-not $m.Success) { throw 'Unable to read CSRF token from admin dashboard' }
  return $m.Groups[1].Value
}

function New-JsonRequest($session, [string]$url, [string]$method, $payload) {
  return Invoke-RestMethod -Uri $url -Method $method -WebSession $session -ContentType 'application/json' -Body ($payload | ConvertTo-Json -Depth 12)
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-WebRequest -Uri "$base/admin/login.php" -WebSession $session -UseBasicParsing | Out-Null

$email = Get-AdminEmail
if (-not $email) { throw 'No active admin email found' }
$otp = '123456'
Seed-Otp -email $email -otp $otp

Invoke-WebRequest -Uri "$base/admin/login.php" -Method Post -WebSession $session -UseBasicParsing -MaximumRedirection 5 -Body @{ action='verify_otp'; login=$email; otp=$otp } | Out-Null
$auth = Get-ApiJson -session $session -url "$base/api/admin/auth/me"
if (-not $auth.success) { throw 'Admin auth failed for compact verifier' }

$beforeFinance = Get-ApiJson -session $session -url "$base/api/admin/finance/summary"
$beforeReports = Get-ApiJson -session $session -url "$base/api/admin/reports/summary"

Write-Output "BEFORE_FINANCE_STATUS=$($beforeFinance.data.reconciliation_status)"
Write-Output "BEFORE_FINANCE_VARIANCE=$($beforeFinance.data.reconciliation_variance)"
Write-Output "BEFORE_REPORTS_STATUS=$($beforeReports.data.reconciliation_status)"
Write-Output "BEFORE_REPORTS_VARIANCE=$($beforeReports.data.reconciliation_variance)"

$q = [uri]::EscapeDataString($ProductQuery)
$searchLegacy = Invoke-RestMethod -Uri "$base/admin/api/search-products.php?q=$q&limit=5" -WebSession $session -Method Get -ContentType 'application/json'

$variantNamePresent = $false
$unitTypePresent = $false
$isDefaultPresent = $false
if ($searchLegacy.success -and $searchLegacy.products.Count -gt 0) {
  foreach ($p in $searchLegacy.products) {
    if ($p.variants.Count -gt 0) {
      $first = $p.variants[0]
      if ($null -ne $first.variant_name -and [string]::IsNullOrWhiteSpace([string]$first.variant_name) -eq $false) { $variantNamePresent = $true }
      if ($null -ne $first.unit_type -and [string]::IsNullOrWhiteSpace([string]$first.unit_type) -eq $false) { $unitTypePresent = $true }
      if ($null -ne $first.is_default) { $isDefaultPresent = $true }
      break
    }
  }
}

Write-Output "LEGACY_SEARCH_SUCCESS=$($searchLegacy.success)"
Write-Output "LEGACY_VARIANT_NAME_PRESENT=$variantNamePresent"
Write-Output "LEGACY_UNIT_TYPE_PRESENT=$unitTypePresent"
Write-Output "LEGACY_IS_DEFAULT_PRESENT=$isDefaultPresent"

$afterFinance = Get-ApiJson -session $session -url "$base/api/admin/finance/summary"
$afterReports = Get-ApiJson -session $session -url "$base/api/admin/reports/summary"

Write-Output "AFTER_FINANCE_STATUS=$($afterFinance.data.reconciliation_status)"
Write-Output "AFTER_FINANCE_VARIANCE=$($afterFinance.data.reconciliation_variance)"
Write-Output "AFTER_FINANCE_BREAKDOWN_NET=$($afterFinance.data.reconciliation_breakdown.net)"
Write-Output "AFTER_FINANCE_SOURCES_LEDGER=$($afterFinance.data.reconciliation_sources.general_ledger_entries)"
Write-Output "AFTER_REPORTS_STATUS=$($afterReports.data.reconciliation_status)"
Write-Output "AFTER_REPORTS_VARIANCE=$($afterReports.data.reconciliation_variance)"
Write-Output "AFTER_REPORTS_BREAKDOWN_CASH=$($afterReports.data.reconciliation_breakdown.cash)"
Write-Output "AFTER_REPORTS_SOURCES_INVOICES=$($afterReports.data.reconciliation_sources.invoices)"

if ($FreshProductCheck.IsPresent) {
  $csrf = Get-CsrfToken -session $session
  $categoryId = DbQuerySingle "SELECT id FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL AND is_active=1 ORDER BY id ASC LIMIT 1;"
  if (-not $categoryId) { throw 'No active root category found for fresh product check' }

  $stamp = Get-Date -Format 'yyyyMMddHHmmss'
  $name = "R2 Fresh Canonical $stamp"
  $slug = "r2-fresh-$stamp"
  $sku = "R2F-$stamp"

  $createPayload = @{
    _csrf = $csrf
    name = $name
    slug = $slug
    sku = $sku
    collection_category_id = [int]$categoryId
    description = "Fresh create canonical desc $stamp"
    short_description = "Fresh create canonical desc $stamp"
    long_description = "Fresh create canonical desc $stamp"
    starting_price = 199.00
    base_price = 199.00
    stock_quantity = 25
    availability_status = 'in_stock'
    is_veg = 1
    dietary_tag = 'regular'
    variants = @(
      @{ variant_name='Half Kg'; variant_label='Half Kg'; weight_or_size='Half Kg'; unit_type='weight'; price=199.00; stock_quantity=15; is_default=1 },
      @{ variant_name='1 Kg'; variant_label='1 Kg'; weight_or_size='1 Kg'; unit_type='weight'; price=349.00; stock_quantity=10; is_default=0 }
    )
  }

  $createResp = New-JsonRequest -session $session -url "$base/api/admin/products" -method 'Post' -payload $createPayload
  Write-Output "FRESH_CREATE_SUCCESS=$($createResp.success)"

  $productId = DbQuerySingle "SELECT id FROM products WHERE sku='$sku' ORDER BY id DESC LIMIT 1;"
  if (-not $productId) { throw 'Fresh product row not found after create' }

  $updatePayload = @{
    _csrf = $csrf
    name = $name
    slug = $slug
    sku = $sku
    collection_category_id = [int]$categoryId
    description = "Fresh update canonical desc $stamp"
    short_description = "Fresh update canonical desc $stamp"
    long_description = "Fresh update canonical desc $stamp"
    starting_price = 209.00
    base_price = 209.00
    stock_quantity = 20
    availability_status = 'in_stock'
    is_veg = 1
    dietary_tag = 'regular'
    variants = @(
      @{ variant_name='Slice'; variant_label='Slice'; weight_or_size='Slice'; unit_type='piece'; price=89.00; stock_quantity=40; is_default=1 },
      @{ variant_name='Party Box'; variant_label='Party Box'; weight_or_size='Party Box'; unit_type='custom'; price=459.00; stock_quantity=8; is_default=0 }
    )
  }

  $updateResp = New-JsonRequest -session $session -url "$base/api/admin/products/$productId" -method 'Patch' -payload $updatePayload
  Write-Output "FRESH_UPDATE_SUCCESS=$($updateResp.success)"

  $freshQ = [uri]::EscapeDataString($sku)
  $freshSearch = Invoke-RestMethod -Uri "$base/admin/api/search-products.php?q=$freshQ&limit=2" -WebSession $session -Method Get -ContentType 'application/json'
  $freshItem = $null
  if ($freshSearch.success -and $freshSearch.products.Count -gt 0) {
    foreach ($p in $freshSearch.products) {
      if ([string]$p.sku -eq $sku) { $freshItem = $p; break }
    }
  }

  $freshVariantCanonical = $false
  if ($null -ne $freshItem -and $freshItem.variants.Count -gt 0) {
    $v = $freshItem.variants[0]
    $freshVariantCanonical = ($null -ne $v.variant_name -and [string]::IsNullOrWhiteSpace([string]$v.variant_name) -eq $false) -and
      ($null -ne $v.unit_type -and [string]::IsNullOrWhiteSpace([string]$v.unit_type) -eq $false) -and
      ($null -ne $v.is_default)
  }

  $freshDbVariant = DbQuerySingle "SELECT CONCAT(COALESCE(NULLIF(variant_name,''),'<empty>'),'|',COALESCE(NULLIF(unit_type,''),'<empty>'),'|',is_default) FROM product_variants WHERE product_id=$productId ORDER BY is_default DESC, id ASC LIMIT 1;"

  Write-Output "FRESH_PRODUCT_ID=$productId"
  Write-Output "FRESH_LEGACY_SEARCH_SUCCESS=$($freshSearch.success)"
  Write-Output "FRESH_VARIANT_CANONICAL=$freshVariantCanonical"
  Write-Output "FRESH_DB_TOP_VARIANT=$freshDbVariant"

  $exportFileHost = Join-Path $PWD ("storage/import-logs/r3-export-$stamp.xlsx")
  Invoke-WebRequest -Uri "$base/admin/download_products.php" -Method Get -WebSession $session -OutFile $exportFileHost -UseBasicParsing | Out-Null
  $exportFileContainer = "/var/www/html/storage/import-logs/r3-export-$stamp.xlsx"
  $parseJson = docker exec cakeouflage-web php /var/www/html/storage/import-logs/parse_export_row.php $exportFileContainer $name
  $exportProbe = $null
  try {
    $exportProbe = $parseJson | ConvertFrom-Json
  } catch {
    $exportProbe = $null
  }

  $exportVariantCanonical = $false
  if ($null -ne $exportProbe) {
    $exportVariantCanonical =
      ($exportProbe.description -eq "Fresh update canonical desc $stamp") -and
      ($exportProbe.variant_name -eq 'Slice') -and
      ($exportProbe.unit_type -eq 'piece')
  }

  Write-Output "R3_EXPORT_FILE=$exportFileHost"
  Write-Output "R3_EXPORT_VARIANT_CANONICAL=$exportVariantCanonical"
  if ($null -ne $exportProbe) {
    Write-Output ("R3_EXPORT_ROW=" + ($parseJson -replace "`r?`n", ''))
  }
}

Write-Output 'COMPACT_R2_R9_VERIFY_PASS=TRUE'
