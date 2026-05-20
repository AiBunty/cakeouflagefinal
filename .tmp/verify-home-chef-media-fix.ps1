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
  $url = "ftp://$hostName/$root" + 'app/Views/pages/home.php'
  $text = & curl.exe -sS --user "$user`:$pass" "$url"
  foreach($marker in @('home_normalize_media_url','chefIsVideo','type="<?= htmlspecialchars($chefMime')){
    if($text -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $url marker=$marker" } else { Write-Output "VERIFY_FAIL $url marker=$marker" }
  }
}
