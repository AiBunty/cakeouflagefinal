param(
    [switch]$Strict,
    [switch]$SkipAuthValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $PSScriptRoot 'runtime/Deploy.Common.ps1')

$config = Get-DeployConfig -RepoRoot $repoRoot
Assert-RequiredConfig -Config $config
$logPath = New-LogPath -RepoRoot $repoRoot -Prefix 'validate'

Write-DeployLog -Path $logPath -Message 'Validation start'
Write-DeployLog -Path $logPath -Message ('FTP host=' + $config.FtpHost + ' user=' + $config.FtpUser)
Write-DeployLog -Path $logPath -Message ('DB host=' + $config.DbHost + ' db=' + $config.DbName)

Test-FtpConnectivity -Config $config -LogPath $logPath | Out-Null
Test-FtpWritableDirectory -Config $config -Directory 'storage' -LogPath $logPath
Test-FtpWritableDirectory -Config $config -Directory 'uploads' -LogPath $logPath
Test-FtpWritableDirectory -Config $config -Directory 'public/uploads' -LogPath $logPath

$dbInfo = Test-DbConnection -Config $config -LogPath $logPath
if ($Strict) {
    $dbCharset = $null
    if ($dbInfo.PSObject.Properties.Name -contains 'cs') {
        $dbCharset = $dbInfo.cs
    } elseif ($dbInfo.PSObject.Properties.Name -contains 'character_set_server') {
        $dbCharset = $dbInfo.character_set_server
    } elseif ($dbInfo.PSObject.Properties.Name -contains '@@character_set_server') {
        $dbCharset = $dbInfo.'@@character_set_server'
    }

    if ([string]::IsNullOrWhiteSpace($dbCharset)) {
        Write-DeployLog -Path $logPath -Message 'WARNING could not determine production DB server charset from diagnostics payload'
    } elseif ($dbCharset -ne 'utf8mb4') {
        Write-DeployLog -Path $logPath -Message ('WARNING production DB server charset is ' + $dbCharset + ' (expected utf8mb4 default locally)')
    }
}

$baseUrl = $config.AppUrl.TrimEnd('/')
Invoke-HttpStatusCheck -Url ($baseUrl + '/') -LogPath $logPath
Invoke-HttpStatusCheck -Url ($baseUrl + '/account') -LogPath $logPath
Invoke-HttpStatusCheck -Url ($baseUrl + '/admin/login.php') -LogPath $logPath
Invoke-HttpStatusCheck -Url ($baseUrl + '/api/health/db') -LogPath $logPath

if (-not $SkipAuthValidation) {
    $authScript = Join-Path $repoRoot 'scripts/qa/live_auth_verify.ps1'
    if (Test-Path $authScript) {
        $authOutput = pwsh -NoProfile -File $authScript 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw ('Auth validation failed: ' + ($authOutput -join "`n"))
        }
        Write-DeployLog -Path $logPath -Message 'Auth validation PASS'
    } else {
        Write-DeployLog -Path $logPath -Message 'Auth validation skipped: scripts/qa/live_auth_verify.ps1 not found'
    }
}

Write-DeployLog -Path $logPath -Message ('Validation complete. Log=' + $logPath)
Write-Output ('VALIDATION_OK ' + $logPath)
