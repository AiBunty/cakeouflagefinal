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
  $bannersUrl = "ftp://$hostName/$root" + 'admin/banners.php'
  $bannersText = & curl.exe -sS --user "$user`:$pass" $bannersUrl
  foreach($marker in @('media_try_optimize_video','Current server upload limit','data-max-upload-bytes')){
    if($bannersText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $bannersUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $bannersUrl marker=$marker" }
  }

  $iniUrl = "ftp://$hostName/$root" + '.user.ini'
  $iniText = & curl.exe -sS --user "$user`:$pass" $iniUrl
  foreach($marker in @('upload_max_filesize = 1024M','post_max_size = 1024M')){
    if($iniText -match [regex]::Escape($marker)){ Write-Output "VERIFY_OK $iniUrl marker=$marker" } else { Write-Output "VERIFY_FAIL $iniUrl marker=$marker" }
  }
}
