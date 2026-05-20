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
        $value = $trimmed.Substring($idx + 1).Trim()
        $map[$key] = $value
    }

    return $map
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$localEnvPath = Join-Path $repoRoot '.env.local'
if (-not (Test-Path $localEnvPath)) {
    throw ".env.local not found at $localEnvPath"
}

$config = Load-KeyValueFile -Path $localEnvPath

$ftpHost = $config['FTP_HOST']
$ftpUser = $config['FTP_USER']
$ftpPass = $config['FTP_PASS']
$sqlFile = $config['SQL_FILE']
$remoteSqlName = $config['REMOTE_SQL_NAME']

if ([string]::IsNullOrWhiteSpace($ftpHost)) { throw 'FTP_HOST is required in .env.local' }
if ([string]::IsNullOrWhiteSpace($ftpUser)) { throw 'FTP_USER is required in .env.local' }
if ([string]::IsNullOrWhiteSpace($ftpPass)) { throw 'FTP_PASS is required in .env.local' }
if ([string]::IsNullOrWhiteSpace($sqlFile)) { throw 'SQL_FILE is required in .env.local' }
if ([string]::IsNullOrWhiteSpace($remoteSqlName)) { $remoteSqlName = 'cakeouflage.sql' }

if (-not (Test-Path $sqlFile)) {
    throw "SQL file not found: $sqlFile"
}

$remoteUrl = "ftp://$ftpHost/$remoteSqlName"

Write-Output "Uploading SQL dump to FTP root as $remoteSqlName ..."
& curl.exe -sS --ftp-create-dirs --user "$ftpUser`:$ftpPass" -T "$sqlFile" "$remoteUrl"
if ($LASTEXITCODE -ne 0) {
    throw 'Upload failed (curl exited non-zero).'
}

Write-Output 'Upload complete. Verifying remote listing...'
$listing = & curl.exe -sS --list-only --user "$ftpUser`:$ftpPass" "ftp://$ftpHost/"
if ($LASTEXITCODE -ne 0) {
    throw 'Upload may have succeeded, but listing failed.'
}

if ($listing -match "(^|\r?\n)$([Regex]::Escape($remoteSqlName))(\r?\n|$)") {
    Write-Output "VERIFY_OK $remoteSqlName is present in FTP root."
} else {
    throw "Verification failed: $remoteSqlName not found in FTP root listing."
}
