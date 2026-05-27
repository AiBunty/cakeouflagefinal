Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"
php "storage/backups/ftp-hotfix/seed_live_otp_for_admin.php" | Out-Null

$tmp = 'storage/backups/ftp-hotfix'
New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$jar = "$tmp/admin-smoke-cookie.txt"
Remove-Item $jar -ErrorAction SilentlyContinue

$loginBase = 'https://cakeouflage.com/admin/login.php'
curl.exe -s -c $jar -b $jar $loginBase -o "$tmp/smoke-login-get.html" | Out-Null
curl.exe -s -D "$tmp/smoke-login-verify-headers.txt" -c $jar -b $jar -X POST -d "login=Dcore&otp=337329&action=verify_otp" $loginBase -o "$tmp/smoke-login-verify.html" | Out-Null

$modules = @(
  'dashboard.php',
  'orders.php',
  'refunds.php',
  'products.php',
  'categories.php',
  'communications.php',
  'crm_settings.php',
  'business-settings.php',
  'manual_order.php',
  'build-your-own-cake.php',
  'slots.php',
  'banners.php',
  'reports.php',
  'accounting.php',
  'invoices.php',
  'customers.php',
  'telecalling.php',
  'media-center.php',
  'users.php',
  'production_plan.php',
  'toppers.php',
  'import-products.php',
  'follow_ups.php',
  'collections_queue.php',
  'crm_report.php?sub_report=users&per_page=20&page=1'
)

$results = @()
foreach ($m in $modules) {
  $url = "https://cakeouflage.com/admin/$m"
  $safe = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($m)).Replace('=','').Replace('/','_').Replace('+','-')
  $hdr = "$tmp/hdr-$safe.txt"
  $body = "$tmp/body-$safe.html"

  curl.exe -s -D $hdr -c $jar -b $jar $url -o $body | Out-Null

  $status = (Get-Content $hdr | Select-String -Pattern '^HTTP/' | Select-Object -Last 1).Line
  $loc = (Get-Content $hdr | Select-String -Pattern '^location:' -CaseSensitive:$false | Select-Object -Last 1).Line
  $text = Get-Content $body -Raw
  $hasServerError = $text -match 'Server error\. Please check storage/logs/php-error\.log'
  $hasLoginForm = $text -match 'Admin Login'

  $results += [pscustomobject]@{
    module = $m
    status = $status
    location = $loc
    server_error = $hasServerError
    login_page = $hasLoginForm
  }
}

$results | ConvertTo-Json -Depth 3 | Set-Content "storage/logs/qa_admin_module_smoke_v3.json"
$results | Format-Table -AutoSize | Out-String -Width 240
