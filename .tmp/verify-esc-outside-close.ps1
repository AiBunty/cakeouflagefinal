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
  foreach($marker in @('event.key === ''Escape''','!catEditor.contains(event.target)','document.addEventListener(''click''')){
    if($catText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $catUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $catUrl marker=$marker" }
  }

  $prodUrl = "ftp://$hostName/$root" + 'admin/products.php'
  $prodText = & curl.exe -sS --user "$user`:$pass" $prodUrl
  foreach($marker in @('event.key === ''Escape''','!prodEditor.contains(event.target)','document.addEventListener(''click''')){
    if($prodText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $prodUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $prodUrl marker=$marker" }
  }
}
