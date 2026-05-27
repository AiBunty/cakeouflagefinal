Set-Location "d:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"
$envJson = php -r '$e=parse_ini_file(".env.production"); echo json_encode(["FTP_HOST"=>$e["FTP_HOST"],"FTP_USER"=>$e["FTP_USER"],"FTP_PASS"=>$e["FTP_PASS"]]);'
$cfg = $envJson | ConvertFrom-Json
$origPath = 'storage/backups/ftp-hotfix/live-.htaccess'
$probeUrl = 'https://cakeouflage.com/runtime_version_probe.php'
$orig = Get-Content $origPath -Raw
$candidates = @(
  'AddHandler application/x-httpd-php82 .php',
  'AddType application/x-httpd-php82 .php',
  'SetHandler application/x-httpd-php82',
  'AddHandler application/x-httpd-ea-php82 .php',
  'AddType application/x-httpd-ea-php82 .php',
  'SetHandler application/x-httpd-ea-php82',
  'AddHandler application/x-httpd-alt-php82___lsphp .php',
  'AddType application/x-httpd-alt-php82___lsphp .php',
  'SetHandler application/x-httpd-alt-php82___lsphp',
  'AddHandler application/x-httpd-alt-php82 .php',
  'AddType application/x-httpd-alt-php82 .php',
  'SetHandler application/x-httpd-alt-php82'
)
$success = $false
$winning = ''
foreach($directive in $candidates){
  $testHt = "# php-handler test`n$directive`n`n$orig"
  $localTest = 'storage/backups/ftp-hotfix/live-.htaccess.test'
  Set-Content -Path $localTest -Value $testHt -NoNewline
  curl.exe -s --ftp-method nocwd --user "$($cfg.FTP_USER):$($cfg.FTP_PASS)" -T $localTest "ftp://$($cfg.FTP_HOST)/.htaccess" | Out-Null
  $out = curl.exe -s $probeUrl
  $ver = (($out -split "`n") | Where-Object { $_ -like 'PHP_VERSION=*' } | Select-Object -First 1)
  Write-Output ("try: " + $directive + " => " + $ver)
  if($ver -match '^PHP_VERSION=8\.'){
    $success = $true
    $winning = $directive
    break
  }
}
if(-not $success){
  Set-Content -Path 'storage/backups/ftp-hotfix/live-.htaccess.restore' -Value $orig -NoNewline
  curl.exe -s --ftp-method nocwd --user "$($cfg.FTP_USER):$($cfg.FTP_PASS)" -T 'storage/backups/ftp-hotfix/live-.htaccess.restore' "ftp://$($cfg.FTP_HOST)/.htaccess" | Out-Null
  Write-Output 'NO_WORKING_HANDLER_OVERRIDE_FOUND'
} else {
  Write-Output ("WINNING_HANDLER=" + $winning)
  curl.exe -s $probeUrl
}
