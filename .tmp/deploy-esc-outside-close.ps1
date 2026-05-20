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
$files=@('admin/categories.php','admin/products.php')
$roots=@("ftp://$hostName/","ftp://$hostName/public_html/cakeouflage.com/")
foreach($f in $files){
  foreach($r in $roots){
    $url = $r + $f
    & curl.exe -sS --ftp-create-dirs --user "$user`:$pass" -T $f $url
    if($LASTEXITCODE -ne 0){ throw "Upload failed for $f -> $url" }
    Write-Output "DEPLOY_OK $url"
  }
}
Write-Output 'DEPLOY_DONE'
