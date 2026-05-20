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
  @{ path='admin/banners.php'; marker='media-upload-overlay' },
  @{ path='admin/revenue_report.php'; marker='reconcile_channel' },
  @{ path='admin/crm_report.php'; marker='crm-report-pagination' },
  @{ path='admin/includes/crm_report_helpers.php'; marker='fetch_crm_report_users_count' },
  @{ path='admin/orders.php'; marker='orders-pagination' }
)
$roots=@('','public_html/cakeouflage.com/')
foreach($root in $roots){
  foreach($c in $checks){
    $url = "ftp://$hostName/$root$($c.path)"
    $text = & curl.exe -sS --user "$user`:$pass" "$url"
    if($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($text)){
      Write-Output "VERIFY_FAIL fetch $url"
      continue
    }
    if($text -match [regex]::Escape($c.marker)){
      Write-Output "VERIFY_OK $url marker=$($c.marker)"
    } else {
      Write-Output "VERIFY_FAIL $url marker=$($c.marker)"
    }
  }
}
