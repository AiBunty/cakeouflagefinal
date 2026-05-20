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
$roots=@('','public_html/cakeouflage.com/')
foreach($root in $roots){
  $catUrl = "ftp://$hostName/$root" + 'admin/categories.php'
  $catText = & curl.exe -sS --user "$user`:$pass" $catUrl
  foreach($marker in @('action="update_category"','js-cat-edit','Back to List','renderParentOptions')){
    if($catText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $catUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $catUrl marker=$marker" }
  }

  $editUrl = "ftp://$hostName/$root" + 'admin/edit-category.php'
  $editText = & curl.exe -sS --user "$user`:$pass" $editUrl
  foreach($marker in @('header(''Location: '' . $target)','focus=')){
    if($editText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $editUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $editUrl marker=$marker" }
  }
}
