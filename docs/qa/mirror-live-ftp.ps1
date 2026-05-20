param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [string]$OutputRoot = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path '_live_mirrors'),
    [string]$SnapshotName = (Get-Date -Format 'yyyyMMdd_HHmmss'),
    [string]$FtpHost = '',
    [string]$FtpUser = '',
    [string]$FtpPass = ''
)

$ErrorActionPreference = 'Stop'

function Load-KeyValueFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    $map = @{}
    foreach ($line in Get-Content -Path $Path) {
        $trimmed = $line.Trim()
        if ([string]::IsNullOrWhiteSpace($trimmed)) { continue }
        if ($trimmed.StartsWith('#')) { continue }

        $idx = $trimmed.IndexOf('=')
        if ($idx -lt 1) { continue }

        $key = $trimmed.Substring(0, $idx).Trim()
        $value = $trimmed.Substring($idx + 1).Trim().Trim('"').Trim("'")
        $map[$key] = $value
    }

    return $map
}

function Get-ConfigMap {
    param([string]$Root)

    $combined = @{}
    $candidates = @(
        (Join-Path $Root '.env.local'),
        (Join-Path $Root '.env')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            $pairs = Load-KeyValueFile -Path $candidate
            foreach ($key in $pairs.Keys) {
                $combined[$key] = $pairs[$key]
            }
        }
    }

    return $combined
}

function Parse-FtpListLine {
    param([string]$Line)

    if ([string]::IsNullOrWhiteSpace($Line)) { return $null }

    if ($Line -match '^(?<perm>[dl-][rwxstST-]{9})\s+\d+\s+\S+\s+\S+\s+\d+\s+\w+\s+\d+\s+([\d:]{4,5}|\d{4})\s+(?<name>.+)$') {
        $entryType = 'file'
        if ($Matches['perm'].StartsWith('d')) {
            $entryType = 'dir'
        }

        return [pscustomobject]@{
            Name = $Matches['name']
            Type = $entryType
        }
    }

    if ($Line -match '^\d{2}-\d{2}-\d{2}\s+\d{2}:\d{2}(AM|PM)\s+(?<size><DIR>|\d+)\s+(?<name>.+)$') {
        $entryType = 'file'
        if ($Matches['size'] -eq '<DIR>') {
            $entryType = 'dir'
        }

        return [pscustomobject]@{
            Name = $Matches['name'].Trim()
            Type = $entryType
        }
    }

    return $null
}

function Encode-FtpPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    $parts = $Path -split '/'
    $encoded = @()
    foreach ($part in $parts) {
        if ($part -eq '') {
            $encoded += ''
            continue
        }
        $encoded += [System.Uri]::EscapeDataString($part)
    }

    return ($encoded -join '/')
}

function Invoke-CurlFtpList {
    param(
        [Parameter(Mandatory = $true)][string]$FtpHost,
        [Parameter(Mandatory = $true)][string]$User,
        [Parameter(Mandatory = $true)][string]$Pass,
        [Parameter(Mandatory = $true)][string]$RemotePath
    )

    $listPath = $RemotePath
    if (-not $listPath.EndsWith('/')) {
        $listPath = $listPath + '/'
    }

    $encodedListPath = Encode-FtpPath -Path $listPath
    $uri = "ftp://$FtpHost$encodedListPath"
    $cred = "$User`:$Pass"
    $output = & curl.exe -sS --connect-timeout 20 --max-time 60 --user $cred $uri 2>&1
    if ($LASTEXITCODE -ne 0) {
        $preview = ($output | Select-Object -First 1)
        throw "curl list failed for $RemotePath (exit $LASTEXITCODE): $preview"
    }

    return $output
}

function Invoke-CurlFtpDownload {
    param(
        [Parameter(Mandatory = $true)][string]$FtpHost,
        [Parameter(Mandatory = $true)][string]$User,
        [Parameter(Mandatory = $true)][string]$Pass,
        [Parameter(Mandatory = $true)][string]$RemotePath,
        [Parameter(Mandatory = $true)][string]$LocalPath
    )

    $parent = Split-Path -Parent $LocalPath
    if (-not (Test-Path $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }

    $encodedRemotePath = Encode-FtpPath -Path $RemotePath
    $uri = "ftp://$FtpHost$encodedRemotePath"
    $cred = "$User`:$Pass"
    & curl.exe -sS --connect-timeout 20 --max-time 120 --user $cred $uri --output $LocalPath 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "curl download failed for $RemotePath (exit $LASTEXITCODE)"
    }
}

$config = Get-ConfigMap -Root $RepoRoot

if ([string]::IsNullOrWhiteSpace($FtpHost)) { $FtpHost = $env:CAKEO_FTP_HOST }
if ([string]::IsNullOrWhiteSpace($FtpUser)) { $FtpUser = $env:CAKEO_FTP_USER }
if ([string]::IsNullOrWhiteSpace($FtpPass)) { $FtpPass = $env:CAKEO_FTP_PASS }

if ([string]::IsNullOrWhiteSpace($FtpHost)) { $FtpHost = $config['FTP_HOST'] }
if ([string]::IsNullOrWhiteSpace($FtpUser)) { $FtpUser = $config['FTP_USER'] }
if ([string]::IsNullOrWhiteSpace($FtpPass)) { $FtpPass = $config['FTP_PASS'] }

if ([string]::IsNullOrWhiteSpace($FtpHost) -or [string]::IsNullOrWhiteSpace($FtpUser) -or [string]::IsNullOrWhiteSpace($FtpPass)) {
    throw 'Missing FTP_HOST / FTP_USER / FTP_PASS in .env.local or .env'
}

if (-not (Test-Path $OutputRoot)) {
    New-Item -ItemType Directory -Path $OutputRoot -Force | Out-Null
}

$snapshotDir = Join-Path $OutputRoot ("live_mirror_" + $SnapshotName)
New-Item -ItemType Directory -Path $snapshotDir -Force | Out-Null

$queue = New-Object System.Collections.Generic.Queue[string]
$queue.Enqueue('/')

$visited = New-Object System.Collections.Generic.HashSet[string]
$downloaded = 0

while ($queue.Count -gt 0) {
    $dir = $queue.Dequeue()
    if (-not $visited.Add($dir)) { continue }

    Write-Output "LIST $dir"
    $lines = Invoke-CurlFtpList -FtpHost $FtpHost -User $FtpUser -Pass $FtpPass -RemotePath $dir

    foreach ($line in $lines) {
        $entry = Parse-FtpListLine -Line $line
        if ($null -eq $entry) { continue }
        if ($entry.Name -eq '.' -or $entry.Name -eq '..') { continue }

        $remotePath = if ($dir -eq '/') { '/' + $entry.Name } else { ($dir.TrimEnd('/') + '/' + $entry.Name) }

        if ($entry.Type -eq 'dir') {
            $localDir = Join-Path $snapshotDir ($remotePath.TrimStart('/') -replace '/', [IO.Path]::DirectorySeparatorChar)
            if (-not (Test-Path $localDir)) {
                New-Item -ItemType Directory -Path $localDir -Force | Out-Null
            }
            $queue.Enqueue($remotePath)
            continue
        }

        $localFile = Join-Path $snapshotDir ($remotePath.TrimStart('/') -replace '/', [IO.Path]::DirectorySeparatorChar)
        Invoke-CurlFtpDownload -FtpHost $FtpHost -User $FtpUser -Pass $FtpPass -RemotePath $remotePath -LocalPath $localFile
        $downloaded++

        if (($downloaded % 100) -eq 0) {
            Write-Output "DOWNLOADED $downloaded files"
        }
    }
}

$manifest = [pscustomobject]@{
    snapshot = Split-Path -Leaf $snapshotDir
    root = $snapshotDir
    downloaded_files = $downloaded
    ftp_host = $FtpHost
    timestamp = (Get-Date).ToString('s')
}

$manifestPath = Join-Path $snapshotDir 'mirror-manifest.json'
$manifest | ConvertTo-Json -Depth 4 | Set-Content -Path $manifestPath -Encoding UTF8

Write-Output "MIRROR_OK path=$snapshotDir files=$downloaded"
