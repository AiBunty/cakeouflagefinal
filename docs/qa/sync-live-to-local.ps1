param(
    [Parameter(Mandatory = $true)][string]$MirrorPath,
    [string]$LocalRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $MirrorPath)) {
    throw "MirrorPath not found: $MirrorPath"
}

$protectedTopLevel = @('.git', '_live_mirrors')

function Get-RelPath {
    param([string]$Root, [string]$Full)
    return $Full.Substring($Root.Length).TrimStart('\\', '/') -replace '\\', '/'
}

$liveFiles = Get-ChildItem -Path $MirrorPath -Recurse -File
$localFiles = Get-ChildItem -Path $LocalRoot -Recurse -File

$liveSet = New-Object System.Collections.Generic.HashSet[string]([System.StringComparer]::OrdinalIgnoreCase)
$localSet = New-Object System.Collections.Generic.HashSet[string]([System.StringComparer]::OrdinalIgnoreCase)

foreach ($f in $liveFiles) {
    $rel = Get-RelPath -Root $MirrorPath -Full $f.FullName
    $liveSet.Add($rel) | Out-Null
}

foreach ($f in $localFiles) {
    $rel = Get-RelPath -Root $LocalRoot -Full $f.FullName

    $isProtected = $false
    foreach ($top in $protectedTopLevel) {
        if ($rel.Equals($top, [System.StringComparison]::OrdinalIgnoreCase) -or $rel.StartsWith($top + '/', [System.StringComparison]::OrdinalIgnoreCase)) {
            $isProtected = $true
            break
        }
    }

    if (-not $isProtected) {
        $localSet.Add($rel) | Out-Null
    }
}

$toDelete = @()
foreach ($rel in $localSet) {
    if (-not $liveSet.Contains($rel)) {
        $toDelete += $rel
    }
}

$copied = 0
$deleted = 0

foreach ($f in $liveFiles) {
    $rel = Get-RelPath -Root $MirrorPath -Full $f.FullName
    $dst = Join-Path $LocalRoot ($rel -replace '/', [IO.Path]::DirectorySeparatorChar)

    $parent = Split-Path -Parent $dst
    if (-not (Test-Path $parent)) {
        if ($Apply) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
    }

    $needsCopy = $true
    if (Test-Path $dst) {
        $srcHash = (Get-FileHash -Path $f.FullName -Algorithm SHA256).Hash
        $dstHash = (Get-FileHash -Path $dst -Algorithm SHA256).Hash
        $needsCopy = $srcHash -ne $dstHash
    }

    if ($needsCopy) {
        if ($Apply) {
            Copy-Item -Path $f.FullName -Destination $dst -Force
        }
        $copied++
    }
}

foreach ($rel in $toDelete) {
    $full = Join-Path $LocalRoot ($rel -replace '/', [IO.Path]::DirectorySeparatorChar)
    if ($Apply) {
        Remove-Item -Path $full -Force
    }
    $deleted++
}

if (-not $Apply) {
    Write-Output ("DRY_RUN copy=" + $copied + " delete=" + $deleted)
    Write-Output 'Run with -Apply to execute destructive sync.'
} else {
    # Remove empty directories after file deletes.
    Get-ChildItem -Path $LocalRoot -Recurse -Directory |
        Sort-Object FullName -Descending |
        ForEach-Object {
            $rel = Get-RelPath -Root $LocalRoot -Full $_.FullName
            foreach ($top in $protectedTopLevel) {
                if ($rel.Equals($top, [System.StringComparison]::OrdinalIgnoreCase) -or $rel.StartsWith($top + '/', [System.StringComparison]::OrdinalIgnoreCase)) {
                    return
                }
            }

            if (-not (Get-ChildItem -Path $_.FullName -Force)) {
                Remove-Item -Path $_.FullName -Force
            }
        }

    Write-Output ("SYNC_OK copy=" + $copied + " delete=" + $deleted)
}
