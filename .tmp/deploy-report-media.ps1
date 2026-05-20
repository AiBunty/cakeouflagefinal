$ErrorActionPreference = "Stop"
function Load-KeyValueFile([string]$Path){
  $map=@{}
  foreach($line in Get-Content -Path $Path){
    $t=$line.Trim()
    if([string]::IsNullOrWhiteSpace($t) -or $t.StartsWith('#')){ continue }
    $i=$t.IndexOf('=')
    if($i -lt 1){ continue }
    $k=$t.Substring(0,$i).Trim()
    $v=$t.Substring($i+1).Trim()
    $map[$k]=$v
  }
  return $map
}

$cfg=Load-KeyValueFile ".env.local"
$hostName=$cfg['FTP_HOST']
$user=$cfg['FTP_USER']
$pass=$cfg['FTP_PASS']
if([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($user) -or [string]::IsNullOrWhiteSpace($pass)){
  throw "FTP credentials missing in .env.local"
}

$files=@(
  "admin/banners.php",
  "admin/revenue_report.php",
  "admin/crm_report.php",
  "admin/includes/crm_report_helpers.php",
  "admin/orders.php"
)
$targets=@(
  "ftp://$hostName/",
  "ftp://$hostName/public_html/cakeouflage.com/"
)

foreach($f in $files){
  if(!(Test-Path $f)){ throw "Missing local file: $f" }
  foreach($t in $targets){
    $url = $t + ($f -replace '\\','/')
    & curl.exe -sS --ftp-create-dirs --user "$user`:$pass" -T "$f" "$url"
    if($LASTEXITCODE -ne 0){ throw "Upload failed for $f -> $url" }
    Write-Output "DEPLOY_OK $url"
  }
}
Write-Output "DEPLOY_DONE"
