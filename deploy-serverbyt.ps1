param(
    [switch]$WhatIf,
    [switch]$RunMigration,
    [switch]$RunValidation,
    [switch]$SkipAudit,
    [string]$MigrationFile = 'database/migrations/serverbyt_sync.sql'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$entryScript = Join-Path $PSScriptRoot 'deploy/deploy-serverbyt.ps1'
if (-not (Test-Path $entryScript)) {
    throw "Permanent deploy entry script not found: $entryScript"
}

& $entryScript -WhatIf:$WhatIf -RunMigration:$RunMigration -RunValidation:$RunValidation -SkipAudit:$SkipAudit -MigrationFile $MigrationFile