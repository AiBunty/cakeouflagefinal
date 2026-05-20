param(
    [Parameter(Mandatory = $true)][string]$MirrorPath,
    [string]$LocalRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [string]$OutputDir = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path '_live_mirrors')
)

$ErrorActionPreference = 'Stop'

function Get-FileMap {
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [string[]]$ExcludePrefixes = @()
    )

    $map = @{}
    $files = Get-ChildItem -Path $Root -Recurse -File

    foreach ($f in $files) {
        $rel = $f.FullName.Substring($Root.Length).TrimStart('\', '/') -replace '\\', '/'

        $skip = $false
        foreach ($prefix in $ExcludePrefixes) {
            if ($rel.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
                $skip = $true
                break
            }
        }
        if ($skip) { continue }

        $hash = (Get-FileHash -Path $f.FullName -Algorithm SHA256).Hash
        $map[$rel] = [pscustomobject]@{
            path = $rel
            size = $f.Length
            hash = $hash
            mtime = $f.LastWriteTimeUtc.ToString('o')
        }
    }

    return $map
}

if (-not (Test-Path $MirrorPath)) {
    throw "MirrorPath not found: $MirrorPath"
}

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$exclude = @('.git/', '_live_mirrors/')
$localMap = Get-FileMap -Root $LocalRoot -ExcludePrefixes $exclude
$liveMap = Get-FileMap -Root $MirrorPath

$missingInLocal = New-Object System.Collections.Generic.List[string]
$extraInLocal = New-Object System.Collections.Generic.List[string]
$changed = New-Object System.Collections.Generic.List[string]

foreach ($path in $liveMap.Keys) {
    if (-not $localMap.ContainsKey($path)) {
        $missingInLocal.Add($path)
        continue
    }

    if ($localMap[$path].hash -ne $liveMap[$path].hash) {
        $changed.Add($path)
    }
}

foreach ($path in $localMap.Keys) {
    if (-not $liveMap.ContainsKey($path)) {
        $extraInLocal.Add($path)
    }
}

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$report = [pscustomobject]@{
    generated_at = (Get-Date).ToString('s')
    mirror_path = (Resolve-Path $MirrorPath).Path
    local_root = (Resolve-Path $LocalRoot).Path
    summary = [pscustomobject]@{
        live_files = $liveMap.Count
        local_files = $localMap.Count
        missing_in_local = $missingInLocal.Count
        extra_in_local = $extraInLocal.Count
        changed = $changed.Count
    }
    missing_in_local = $missingInLocal | Sort-Object
    extra_in_local = $extraInLocal | Sort-Object
    changed = $changed | Sort-Object
}

$jsonPath = Join-Path $OutputDir ("parity-report_" + $stamp + '.json')
$report | ConvertTo-Json -Depth 8 | Set-Content -Path $jsonPath -Encoding UTF8

Write-Output ("PARITY_REPORT " + $jsonPath)
Write-Output ("SUMMARY live=" + $liveMap.Count + " local=" + $localMap.Count + " missing=" + $missingInLocal.Count + " extra=" + $extraInLocal.Count + " changed=" + $changed.Count)
