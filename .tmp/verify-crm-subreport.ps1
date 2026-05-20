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
$markers=@('sub_report','Main category with sub-reports','selectedSubReport === ''users''')
$roots=@('','public_html/cakeouflage.com/')
foreach($root in $roots){
  $url = "ftp://$hostName/$root" + 'admin/crm_report.php'
  $text = & curl.exe -sS --user "$user`:$pass" "$url"
  foreach($m in $markers){
    if($text -match [regex]::Escape($m)){ Write-Output "VERIFY_OK $url marker=$m" } else { Write-Output "VERIFY_FAIL $url marker=$m" }
  }
}
