$ErrorActionPreference = "Stop"
function Load-KeyValueFile([string]$Path){
  $map=@{}
  foreach($line in Get-Content -Path $Path){
    $t=$line.Trim(); if([string]::IsNullOrWhiteSpace($t) -or $t.StartsWith('#')){ continue }
    $i=$t.IndexOf('='); if($i -lt 1){ continue }
    $map[$t.Substring(0,$i).Trim()] = $t.Substring($i+1).Trim()
  }
  return $map
}
$cfg=Load-KeyValueFile '.env.local'
$hostName=$cfg['FTP_HOST']; $user=$cfg['FTP_USER']; $pass=$cfg['FTP_PASS']
if([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($user) -or [string]::IsNullOrWhiteSpace($pass)){ throw 'FTP credentials missing' }
$uploads = @(
  @{ local='admin/banners.php'; remote='admin/banners.php' },
  @{ local='.user.ini'; remote='.user.ini' }
)
$roots=@("ftp://$hostName/","ftp://$hostName/public_html/cakeouflage.com/")
foreach($item in $uploads){
  foreach($root in $roots){
    $url = $root + $item.remote
    & curl.exe -sS --ftp-create-dirs --user "$user`:$pass" -T $item.local $url
    if($LASTEXITCODE -ne 0){ throw "Upload failed for $($item.local) -> $url" }
    Write-Output "DEPLOY_OK $url"
  }
}
Write-Output 'DEPLOY_DONE'
