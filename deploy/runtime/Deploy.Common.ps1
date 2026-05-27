Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-RepoRoot {
    param([string]$ScriptPath)
    $resolved = Resolve-Path $ScriptPath
    return Split-Path -Parent (Split-Path -Parent $resolved)
}

function Read-EnvFile {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        throw "Environment file not found: $Path"
    }

    $map = @{}
    Get-Content $Path | ForEach-Object {
        if ($_ -match '^(?!\s*#)([^=]+)=(.*)$') {
            $key = $matches[1].Trim()
            $value = $matches[2]
            if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $map[$key] = $value
        }
    }
    return $map
}

function Get-DeployConfig {
    param([string]$RepoRoot)

    $envPath = Join-Path $RepoRoot '.env.production'
    $envMap = Read-EnvFile -Path $envPath

    return [pscustomobject]@{
        RepoRoot = $RepoRoot
        EnvPath = $envPath
        Env = $envMap
        AppUrl = $envMap['APP_URL']
        FtpHost = $envMap['FTP_HOST']
        FtpPort = if ([string]::IsNullOrWhiteSpace($envMap['FTP_PORT'])) { 21 } else { [int]$envMap['FTP_PORT'] }
        FtpUser = $envMap['FTP_USER']
        FtpPass = $envMap['FTP_PASS']
        DbHost = $envMap['DB_HOST']
        DbPort = if ([string]::IsNullOrWhiteSpace($envMap['DB_PORT'])) { 3306 } else { [int]$envMap['DB_PORT'] }
        DbName = if (-not [string]::IsNullOrWhiteSpace($envMap['DB_NAME'])) { $envMap['DB_NAME'] } else { $envMap['DB_DATABASE'] }
        DbUser = if (-not [string]::IsNullOrWhiteSpace($envMap['DB_USER'])) { $envMap['DB_USER'] } else { $envMap['DB_USERNAME'] }
        DbPass = $envMap['DB_PASSWORD']
    }
}

function Assert-RequiredConfig {
    param([pscustomobject]$Config)

    $required = @(
        'APP_URL',
        'FTP_HOST',
        'FTP_USER',
        'FTP_PASS',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD'
    )

    $missing = New-Object System.Collections.Generic.List[string]
    foreach ($name in $required) {
        if (-not $Config.Env.ContainsKey($name) -or [string]::IsNullOrWhiteSpace($Config.Env[$name])) {
            $missing.Add($name) | Out-Null
        }
    }

    if ($missing.Count -gt 0) {
        throw ('Missing required .env.production keys: ' + ($missing -join ', '))
    }
}

function New-LogPath {
    param(
        [string]$RepoRoot,
        [string]$Prefix
    )

    $dir = Join-Path $RepoRoot 'deploy/logs'
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    return Join-Path $dir ($Prefix + '-' + $timestamp + '.log')
}

function Write-DeployLog {
    param(
        [string]$Path,
        [string]$Message
    )

    $line = "[{0}] {1}" -f (Get-Date -Format 's'), $Message
    Add-Content -Path $Path -Value $line
    Write-Output $line
}

function Mask-Secret {
    param([string]$Value)
    if ([string]::IsNullOrWhiteSpace($Value)) { return '[empty]' }
    if ($Value.Length -le 4) { return '****' }
    return $Value.Substring(0, 2) + ('*' * ($Value.Length - 4)) + $Value.Substring($Value.Length - 2)
}

function Get-FtpCredential {
    param([pscustomobject]$Config)
    return New-Object System.Net.NetworkCredential($Config.FtpUser, $Config.FtpPass)
}

function New-FtpRequest {
    param(
        [string]$Uri,
        [string]$Method,
        [System.Net.NetworkCredential]$Credential
    )

    $request = [System.Net.FtpWebRequest]::Create($Uri)
    $request.Method = $Method
    $request.Credentials = $Credential
    $request.UsePassive = $true
    $request.UseBinary = $true
    $request.KeepAlive = $false
    return $request
}

function Invoke-WithRetry {
    param(
        [scriptblock]$Action,
        [string]$Label,
        [int]$MaxAttempts = 3,
        [int]$DelayMs = 750
    )

    $lastError = $null
    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try {
            return & $Action
        } catch {
            $lastError = $_
            if ($attempt -lt $MaxAttempts) {
                Start-Sleep -Milliseconds $DelayMs
            }
        }
    }
    throw "Operation failed after retries ($Label): $($lastError.Exception.Message)"
}

function Test-FtpConnectivity {
    param(
        [pscustomobject]$Config,
        [string]$LogPath
    )

    $credential = Get-FtpCredential -Config $Config
    $uri = "ftp://$($Config.FtpHost)/"

    $listing = Invoke-WithRetry -Label 'FTP LIST root' -Action {
        $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::ListDirectoryDetails) -Credential $credential
        $response = $request.GetResponse()
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        $text = $reader.ReadToEnd()
        $reader.Close()
        $response.Close()
        $text
    }

    Write-DeployLog -Path $LogPath -Message "FTP connectivity OK host=$($Config.FtpHost) user=$($Config.FtpUser)"
    return $listing
}

function Ensure-FtpDirectory {
    param(
        [pscustomobject]$Config,
        [string]$Path
    )

    if ([string]::IsNullOrWhiteSpace($Path)) { return }

    $credential = Get-FtpCredential -Config $Config
    $segments = $Path.Replace('\\', '/').Split('/', [System.StringSplitOptions]::RemoveEmptyEntries)
    $current = ''
    foreach ($segment in $segments) {
        $current = if ($current) { "$current/$segment" } else { $segment }
        $uri = "ftp://$($Config.FtpHost)/$current"
        try {
            $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::MakeDirectory) -Credential $credential
            $response = $request.GetResponse()
            $response.Close()
        } catch {
            $msg = $_.Exception.Message
            if ($msg -notmatch 'exists|file unavailable') {
                throw
            }
        }
    }
}

function Upload-FtpFile {
    param(
        [pscustomobject]$Config,
        [string]$LocalPath,
        [string]$RemotePath,
        [switch]$WhatIf
    )

    $credential = Get-FtpCredential -Config $Config
    $normalized = $RemotePath.Replace('\\', '/')
    $remoteDir = Split-Path $normalized -Parent
    if ($remoteDir -and $remoteDir -ne '.') {
        Ensure-FtpDirectory -Config $Config -Path $remoteDir
    }

    if ($WhatIf) {
        return
    }

    Invoke-WithRetry -Label "FTP upload $normalized" -Action {
        $uri = "ftp://$($Config.FtpHost)/$normalized"
        $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile) -Credential $credential
        $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
        $request.ContentLength = $bytes.Length
        $stream = $request.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $response = $request.GetResponse()
        $response.Close()
    } | Out-Null
}

function Remove-FtpFile {
    param(
        [pscustomobject]$Config,
        [string]$RemotePath,
        [switch]$WhatIf
    )

    if ($WhatIf) { return }

    $credential = Get-FtpCredential -Config $Config
    $uri = "ftp://$($Config.FtpHost)/$($RemotePath.Replace('\\', '/'))"
    Invoke-WithRetry -Label "FTP delete $RemotePath" -Action {
        $request = New-FtpRequest -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::DeleteFile) -Credential $credential
        $response = $request.GetResponse()
        $response.Close()
    } | Out-Null
}

function Test-FtpWritableDirectory {
    param(
        [pscustomobject]$Config,
        [string]$Directory,
        [string]$LogPath,
        [switch]$WhatIf
    )

    $tempName = 'deploy_probe_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.txt'
    $tempFile = Join-Path $env:TEMP $tempName
    Set-Content -Path $tempFile -Value 'probe' -Encoding ascii
    $remotePath = ($Directory.TrimEnd('/') + '/' + $tempName)

    try {
        Upload-FtpFile -Config $Config -LocalPath $tempFile -RemotePath $remotePath -WhatIf:$WhatIf
        Remove-FtpFile -Config $Config -RemotePath $remotePath -WhatIf:$WhatIf
        Write-DeployLog -Path $LogPath -Message "Writable check PASS path=$Directory"
    } finally {
        Remove-Item $tempFile -ErrorAction SilentlyContinue -Force
    }
}

function Test-DbConnection {
    param(
        [pscustomobject]$Config,
        [string]$LogPath
    )

    $phpScript = @'
<?php
$env = parse_ini_file($argv[1]);
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$db = $env['DB_NAME'] ?? ($env['DB_DATABASE'] ?? '');
$user = $env['DB_USER'] ?? ($env['DB_USERNAME'] ?? '');
$pass = $env['DB_PASSWORD'] ?? '';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$row = $pdo->query("SELECT VERSION() AS version, @@character_set_server AS cs, @@collation_server AS coll, @@sql_mode AS sql_mode")->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_UNESCAPED_SLASHES);
'@

    $tmp = Join-Path $env:TEMP 'deploy_db_check.php'
    Set-Content -Path $tmp -Value $phpScript -Encoding UTF8
    try {
        $raw = php $tmp $Config.EnvPath
        if ($LASTEXITCODE -ne 0) {
            throw 'Production DB connection failed.'
        }
        $obj = $raw | ConvertFrom-Json
        Write-DeployLog -Path $LogPath -Message "DB connectivity OK version=$($obj.version) charset=$($obj.cs) collation=$($obj.coll)"
        return $obj
    } finally {
        Remove-Item $tmp -ErrorAction SilentlyContinue -Force
    }
}

function Invoke-HttpStatusCheck {
    param(
        [string]$Url,
        [string]$LogPath
    )

    $response = Invoke-WebRequest -Uri $Url -Method Get -AllowInsecureRedirect
    Write-DeployLog -Path $LogPath -Message "HTTP $Url => $($response.StatusCode)"
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        throw ('Unexpected HTTP status for {0}: {1}' -f $Url, $response.StatusCode)
    }
}

function Find-WinScpCli {
    $candidates = @(
        "$env:ProgramFiles\\WinSCP\\WinSCP.com",
        "${env:ProgramFiles(x86)}\\WinSCP\\WinSCP.com",
        "$env:LOCALAPPDATA\\Programs\\WinSCP\\WinSCP.com"
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) { return $candidate }
    }

    $cmd = Get-Command 'WinSCP.com' -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    throw 'WinSCP CLI not found. Install WinSCP and ensure WinSCP.com is available.'
}

function Invoke-WinScpScript {
    param(
        [string]$WinScpExe,
        [string]$ScriptPath,
        [string]$LogPath,
        [switch]$WhatIf
    )

    if ($WhatIf) {
        Write-DeployLog -Path $LogPath -Message "WHATIF WinSCP script prepared: $ScriptPath"
        return
    }

    $output = & $WinScpExe "/log=$LogPath" "/ini=nul" "/script=$ScriptPath" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ("WinSCP sync failed: " + ($output -join "`n"))
    }
}

function Invoke-ServerMigration {
    param(
        [pscustomobject]$Config,
        [string]$MigrationFile,
        [string]$LogPath,
        [switch]$WhatIf
    )

    if (-not (Test-Path $MigrationFile)) {
        throw "Migration file not found: $MigrationFile"
    }

    if ($WhatIf) {
        Write-DeployLog -Path $LogPath -Message "WHATIF migration skipped: $MigrationFile"
        return
    }

    $runner = @'
<?php
$env = parse_ini_file($argv[1]);
$sqlPath = $argv[2];
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$db = $env['DB_NAME'] ?? ($env['DB_DATABASE'] ?? '');
$user = $env['DB_USER'] ?? ($env['DB_USERNAME'] ?? '');
$pass = $env['DB_PASSWORD'] ?? '';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$raw = file_get_contents($sqlPath);
$chunks = preg_split('/;\s*(?:\r?\n|$)/', (string)$raw);
foreach ($chunks as $chunk) {
    $sql = trim($chunk);
    if ($sql === '' || str_starts_with($sql, '--')) {
        continue;
    }
    $pdo->exec($sql);
}
echo "MIGRATION_OK\n";
'@

    $tmp = Join-Path $env:TEMP 'deploy_migrate_runner.php'
    Set-Content -Path $tmp -Value $runner -Encoding UTF8
    try {
        $result = php $tmp $Config.EnvPath $MigrationFile 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Migration failed: ' + ($result -join "`n"))
        }
        Write-DeployLog -Path $LogPath -Message 'Migration executed successfully.'
    } finally {
        Remove-Item $tmp -ErrorAction SilentlyContinue -Force
    }
}

function Get-DeploymentStatePaths {
    param([string]$RepoRoot)
    $base = Join-Path $RepoRoot 'storage/deployment'
    New-Item -ItemType Directory -Force -Path $base | Out-Null
    return [pscustomobject]@{
        Base = $base
        Lock = Join-Path $base 'deployment-lock.json'
        History = Join-Path $base 'deployment-history.json'
    }
}

function Acquire-DeploymentLock {
    param(
        [string]$LockPath,
        [string]$Operation,
        [string]$Operator
    )

    if (Test-Path $LockPath) {
        $existingRaw = Get-Content $LockPath -Raw
        if (-not [string]::IsNullOrWhiteSpace($existingRaw)) {
            try {
                $existingObj = $existingRaw | ConvertFrom-Json
                if ($existingObj.locked -eq $true) {
                    throw "Deployment lock already active: $existingRaw"
                }
            } catch {
                throw "Deployment lock file is not valid JSON: $existingRaw"
            }
        }
    }

    $payload = [ordered]@{
        locked = $true
        operation = $Operation
        operator = $Operator
        started_at = (Get-Date).ToString('o')
        host = $env:COMPUTERNAME
        pid = $PID
    }
    $payload | ConvertTo-Json -Depth 4 | Set-Content -Path $LockPath -Encoding UTF8
}

function Release-DeploymentLock {
    param([string]$LockPath)

    $payload = [ordered]@{
        locked = $false
        operation = $null
        operator = $null
        started_at = $null
        host = $null
        pid = $null
    }
    $payload | ConvertTo-Json -Depth 4 | Set-Content -Path $LockPath -Encoding UTF8
}

function Ensure-DeploymentHistoryFile {
    param([string]$Path)
    if (-not (Test-Path $Path)) {
        '[]' | Set-Content -Path $Path -Encoding UTF8
    }
}

function Get-GitContext {
    param([string]$RepoRoot)
    $ctx = [ordered]@{
        commit = 'unknown'
        branch = 'unknown'
        dirty = $false
    }
    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) { return [pscustomobject]$ctx }

    Push-Location $RepoRoot
    try {
        $commit = git rev-parse --short HEAD 2>$null
        if ($LASTEXITCODE -eq 0) { $ctx.commit = $commit.Trim() }
        $branch = git rev-parse --abbrev-ref HEAD 2>$null
        if ($LASTEXITCODE -eq 0) { $ctx.branch = $branch.Trim() }
        $status = git status --porcelain 2>$null
        if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace(($status -join ''))) {
            $ctx.dirty = $true
        }
    } finally {
        Pop-Location
    }

    return [pscustomobject]$ctx
}

function Get-RepoRelativePath {
    param(
        [string]$RepoRoot,
        [string]$AbsolutePath
    )
    return [System.IO.Path]::GetRelativePath($RepoRoot, $AbsolutePath).Replace('\\', '/')
}

function Append-DeploymentHistory {
    param(
        [string]$HistoryPath,
        [hashtable]$Entry
    )

    Ensure-DeploymentHistoryFile -Path $HistoryPath
    $current = Get-Content $HistoryPath -Raw | ConvertFrom-Json
    $list = New-Object System.Collections.Generic.List[object]
    foreach ($row in $current) { $list.Add($row) | Out-Null }
    $list.Add([pscustomobject]$Entry) | Out-Null
    $list | ConvertTo-Json -Depth 10 | Set-Content -Path $HistoryPath -Encoding UTF8
}

function Get-ChangedFilesFromGit {
    param([string]$RepoRoot)

    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) { return @() }

    Push-Location $RepoRoot
    try {
        $files = git diff --name-only HEAD~1 HEAD 2>$null
        if ($LASTEXITCODE -ne 0) { return @() }
        return @($files | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    } finally {
        Pop-Location
    }
}
