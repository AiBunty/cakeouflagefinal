param(
    [Parameter(Mandatory = $true)]
    [string[]]$Files,
    [switch]$WhatIf,
    [switch]$RunValidation,
    [switch]$SkipAudit
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $PSScriptRoot 'runtime/Deploy.Common.ps1')

$config = Get-DeployConfig -RepoRoot $repoRoot
Assert-RequiredConfig -Config $config
$state = Get-DeploymentStatePaths -RepoRoot $repoRoot
$git = Get-GitContext -RepoRoot $repoRoot
$logPath = New-LogPath -RepoRoot $repoRoot -Prefix 'hotfix'
$start = Get-Date

Acquire-DeploymentLock -LockPath $state.Lock -Operation 'hotfix' -Operator $env:USERNAME

$releaseId = (Get-Date -Format 'yyyyMMddHHmmss') + '-hotfix-' + $git.commit
$success = $false
$errorMessage = ''
$uploaded = New-Object System.Collections.Generic.List[string]

Write-DeployLog -Path $logPath -Message ('Hotfix release id=' + $releaseId)
Write-DeployLog -Path $logPath -Message ('Git branch=' + $git.branch + ' commit=' + $git.commit + ' dirty=' + $git.dirty)

try {
    if (-not $SkipAudit) {
        Write-DeployLog -Path $logPath -Message 'Running pre-hotfix validation gate'
        $validateResult = pwsh -NoProfile -File (Join-Path $PSScriptRoot 'deploy-validate.ps1') -Strict -SkipAuthValidation 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Pre-hotfix validation failed: ' + ($validateResult -join "`n"))
        }
    }

    foreach ($relative in $Files) {
        $normalized = $relative.Replace('\\', '/').Trim()
        if ([string]::IsNullOrWhiteSpace($normalized)) { continue }
        if ($normalized -eq '.env' -or $normalized -eq '.env.production') {
            throw 'Hotfix denied: attempting to deploy environment file.'
        }
        if ($normalized.StartsWith('uploads/') -or $normalized.StartsWith('public/uploads/')) {
            throw 'Hotfix denied: attempting to deploy media runtime path.'
        }

        $absolute = Join-Path $repoRoot $normalized
        if (-not (Test-Path $absolute -PathType Leaf)) {
            throw ('Hotfix file not found: ' + $normalized)
        }

        Upload-FtpFile -Config $config -LocalPath $absolute -RemotePath $normalized -WhatIf:$WhatIf
        $uploaded.Add($normalized) | Out-Null
        Write-DeployLog -Path $logPath -Message ('Hotfix uploaded ' + $normalized)
    }

    if ($RunValidation) {
        $post = pwsh -NoProfile -File (Join-Path $PSScriptRoot 'deploy-validate.ps1') -SkipAuthValidation 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Post-hotfix validation failed: ' + ($post -join "`n"))
        }
        Write-DeployLog -Path $logPath -Message 'Post-hotfix validation PASS'
    }

    $success = $true
    Write-DeployLog -Path $logPath -Message 'Hotfix deploy complete'
} catch {
    $errorMessage = $_.Exception.Message
    Write-DeployLog -Path $logPath -Message ('Hotfix failed: ' + $errorMessage)
    throw
} finally {
    $end = Get-Date
    $entry = @{
        release_id = $releaseId
        operation = 'hotfix'
        started_at = $start.ToString('o')
        finished_at = $end.ToString('o')
        actor = $env:USERNAME
        host = $env:COMPUTERNAME
        git_branch = $git.branch
        git_commit = $git.commit
        git_dirty = $git.dirty
        migration = $false
        validation = [bool]$RunValidation
        dry_run = [bool]$WhatIf
        success = $success
        error = $errorMessage
        uploaded_files = @($uploaded)
        log_path = $logPath
    }
    Append-DeploymentHistory -HistoryPath $state.History -Entry $entry
    Release-DeploymentLock -LockPath $state.Lock
}

Write-Output ('HOTFIX_OK ' + $releaseId)
