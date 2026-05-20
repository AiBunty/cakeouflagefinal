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
$checks=@(
  @{ path='admin/update_order_status.php'; marker='automation hooks fail' },
  @{ path='admin/update_order.php'; marker='automation hooks fail' },
  @{ path='admin/order_invoice.php'; marker='auto_print' },
  @{ path='admin/revenue_report.php'; marker='sub_report' }
)
$roots=@('','public_html/cakeouflage.com/')
foreach($root in $roots){
  foreach($c in $checks){
    $url = "ftp://$hostName/$root$($c.path)"
    $text = & curl.exe -sS --user "$user`:$pass" "$url"
    if($text -match [regex]::Escape($c.marker)){ Write-Output "VERIFY_OK $url marker=$($c.marker)" }
    else { Write-Output "VERIFY_FAIL $url marker=$($c.marker)" }
  }
}
