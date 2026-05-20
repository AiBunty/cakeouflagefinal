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
$targets=@("ftp://$hostName/admin/crm_report.php","ftp://$hostName/public_html/cakeouflage.com/admin/crm_report.php")
foreach($url in $targets){
  & curl.exe -sS --ftp-create-dirs --user "$user`:$pass" -T "admin/crm_report.php" "$url"
  if($LASTEXITCODE -ne 0){ throw "Upload failed: $url" }
  Write-Output "DEPLOY_OK $url"
}
Write-Output 'DEPLOY_DONE'
