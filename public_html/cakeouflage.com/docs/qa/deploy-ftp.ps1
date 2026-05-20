$ErrorActionPreference = 'Stop'

$root = Split-Path -Path $PSScriptRoot -Parent | Split-Path -Parent
$hostName = $env:CAKEO_FTP_HOST
$userName = $env:CAKEO_FTP_USER
$password = $env:CAKEO_FTP_PASS

if ([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($userName) -or [string]::IsNullOrWhiteSpace($password)) {
    throw 'Set CAKEO_FTP_HOST, CAKEO_FTP_USER, and CAKEO_FTP_PASS environment variables before running deploy-ftp.ps1'
}

Set-Location $root

$paths = @()
$paths += Get-ChildItem app -Recurse -File | ForEach-Object { $_.FullName }
$paths += Get-ChildItem client -Recurse -File | ForEach-Object { $_.FullName }
$paths += Get-ChildItem database -Recurse -File | ForEach-Object { $_.FullName }
$paths += Get-ChildItem docs -Recurse -File | ForEach-Object { $_.FullName }

$rootFiles = @('index.php', 'ping.php', 'README.md', 'seed_demo.php', '_dbtest.php', 'whereami.txt', '.htaccess')
foreach ($file in $rootFiles) {
    $fullPath = Join-Path $root $file
    if (Test-Path $fullPath) {
        $paths += $fullPath
    }
}

$paths = $paths |
    Where-Object {
        $_ -notmatch '\\.env($|\\.)' -and
        $_ -notmatch '\\.git\\' -and
        $_ -notmatch '\\storage\\' -and
        $_ -notmatch '\\uploads\\'
    } |
    Sort-Object -Unique

$count = 0
foreach ($local in $paths) {
    $relative = $local.Substring($root.Length).TrimStart('\', '/')
    $remote = 'ftp://' + $hostName + '/' + ($relative -replace '\\', '/')

    & curl.exe -s --ftp-create-dirs --user "$userName`:$password" -T "$local" "$remote" | Out-Null

    if ($LASTEXITCODE -ne 0) {
        Write-Output "FAILED $relative"
        exit 1
    }

    $count++
    if (($count % 50) -eq 0) {
        Write-Output "Uploaded $count files..."
    }
}

Write-Output "UPLOAD_OK files=$count"
