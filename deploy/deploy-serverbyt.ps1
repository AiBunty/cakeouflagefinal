param(
    [switch]$WhatIf,
    [switch]$RunMigration,
    [switch]$RunValidation,
    [switch]$SkipAudit,
    [string]$MigrationFile = 'database/migrations/serverbyt_sync.sql'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $PSScriptRoot 'runtime/Deploy.Common.ps1')

$config = Get-DeployConfig -RepoRoot $repoRoot
Assert-RequiredConfig -Config $config
$state = Get-DeploymentStatePaths -RepoRoot $repoRoot
$git = Get-GitContext -RepoRoot $repoRoot
$logPath = New-LogPath -RepoRoot $repoRoot -Prefix 'deploy'
$operation = if ($WhatIf) { 'dry-run' } elseif ($RunMigration) { 'deploy+migration' } else { 'deploy-files' }
$uploadedFiles = New-Object System.Collections.Generic.List[string]
$start = Get-Date

Acquire-DeploymentLock -LockPath $state.Lock -Operation $operation -Operator $env:USERNAME

$releaseId = (Get-Date -Format 'yyyyMMddHHmmss') + '-' + $git.commit
Write-DeployLog -Path $logPath -Message ('Release id=' + $releaseId)
Write-DeployLog -Path $logPath -Message ('Git branch=' + $git.branch + ' commit=' + $git.commit + ' dirty=' + $git.dirty)
Write-DeployLog -Path $logPath -Message ('WhatIf=' + $WhatIf + ' RunMigration=' + $RunMigration + ' RunValidation=' + $RunValidation)

$success = $false
$errorMessage = ''

try {
    if (-not $SkipAudit) {
        Write-DeployLog -Path $logPath -Message 'Running pre-deploy validation gate'
        $validateResult = pwsh -NoProfile -File (Join-Path $PSScriptRoot 'deploy-validate.ps1') -Strict 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Pre-deploy validation failed: ' + ($validateResult -join "`n"))
        }
        Write-DeployLog -Path $logPath -Message 'Pre-deploy validation gate PASS'
    } else {
        Write-DeployLog -Path $logPath -Message 'Pre-deploy validation skipped by -SkipAudit'
    }

    $winScpPath = Find-WinScpCli
    $templatePath = Join-Path $PSScriptRoot 'winscp-sync.txt'
    $fileMaskPath = Join-Path $PSScriptRoot 'excludes.txt'
    if (-not (Test-Path $templatePath)) {
        throw ('WinSCP template not found: ' + $templatePath)
    }

    $rawMaskLines = Get-Content $fileMaskPath | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    $mask = '| ' + (($rawMaskLines -join '; ').Replace('\\', '/'))

    $userEnc = [System.Uri]::EscapeDataString($config.FtpUser)
    $passEnc = [System.Uri]::EscapeDataString($config.FtpPass)
    $sessionUrl = "ftp://$userEnc`:$passEnc@$($config.FtpHost)/"

    $template = Get-Content $templatePath -Raw
    $scriptBody = $template.Replace('__SESSION_URL__', $sessionUrl)
    $scriptBody = $scriptBody.Replace('__FILEMASK__', $mask)
    $scriptBody = $scriptBody.Replace('__LOCAL_ROOT__', $repoRoot)

    $tmpScript = Join-Path $env:TEMP ('winscp-sync-' + [guid]::NewGuid().ToString('N') + '.txt')
    Set-Content -Path $tmpScript -Value $scriptBody -Encoding ASCII

    try {
        Write-DeployLog -Path $logPath -Message ('Starting file sync via WinSCP at ' + $winScpPath)
        Invoke-WinScpScript -WinScpExe $winScpPath -ScriptPath $tmpScript -LogPath $logPath -WhatIf:$WhatIf
        Write-DeployLog -Path $logPath -Message 'File sync complete'
    } finally {
        Remove-Item $tmpScript -ErrorAction SilentlyContinue -Force
    }

    $changed = Get-ChangedFilesFromGit -RepoRoot $repoRoot
    foreach ($item in $changed) {
        if (-not [string]::IsNullOrWhiteSpace($item)) {
            $uploadedFiles.Add($item) | Out-Null
        }
    }

    if ($RunMigration) {
        $migrationPath = Join-Path $repoRoot $MigrationFile
        Write-DeployLog -Path $logPath -Message ('Running additive migration file=' + $MigrationFile)
        Invoke-ServerMigration -Config $config -MigrationFile $migrationPath -LogPath $logPath -WhatIf:$WhatIf
    }

    if ($RunValidation) {
        Write-DeployLog -Path $logPath -Message 'Running post-deploy validation gate'
        $postResult = pwsh -NoProfile -File (Join-Path $PSScriptRoot 'deploy-validate.ps1') -Strict 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Post-deploy validation failed: ' + ($postResult -join "`n"))
        }
        Write-DeployLog -Path $logPath -Message 'Post-deploy validation gate PASS'
    }

    $success = $true
    Write-DeployLog -Path $logPath -Message 'Deployment completed successfully'
} catch {
    $errorMessage = $_.Exception.Message
    Write-DeployLog -Path $logPath -Message ('Deployment failed: ' + $errorMessage)
    throw
} finally {
    $end = Get-Date
    $entry = @{
        release_id = $releaseId
        operation = $operation
        started_at = $start.ToString('o')
        finished_at = $end.ToString('o')
        actor = $env:USERNAME
        host = $env:COMPUTERNAME
        git_branch = $git.branch
        git_commit = $git.commit
        git_dirty = $git.dirty
        migration = [bool]$RunMigration
        validation = [bool]$RunValidation
        dry_run = [bool]$WhatIf
        success = $success
        error = $errorMessage
        uploaded_files = @($uploadedFiles)
        log_path = $logPath
    }
    Append-DeploymentHistory -HistoryPath $state.History -Entry $entry
    Release-DeploymentLock -LockPath $state.Lock
}

Write-Output ('DEPLOYMENT_OK ' + $releaseId)
