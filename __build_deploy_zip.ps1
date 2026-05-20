<#
.SYNOPSIS
  Build deployment ZIP and upload it + the extractor to FTP.
  Run from workspace root:  .\__build_deploy_zip.ps1
#>
param(
    [string]$FtpHost   = "ftp.theboxerp.com",
    [string]$FtpUser   = "admin@cakeouflage.com",
    [string]$FtpPass   = "Zebra@789",
    [string]$RemoteBase = "public_html/",
    [switch]$EnableSsl
)

$ErrorActionPreference = 'Stop'
$SourceDir = $PSScriptRoot
$ZipName   = "cakeouflage_deploy_20260520.zip"
$ZipPath   = Join-Path $SourceDir $ZipName

# ── Files to include/exclude ──────────────────────────────────────────────────
$ExcludePrefixLower = @(
    '.git\',
    '_live_mirrors\',
    'node_modules\',
    'storage\cache\',
    'storage\logs\',
    'storage\import-backups\',
    'storage\import-test\',
    'docs\',
    'client\',
    'admin\backups\',
    'public\uploads\'          # Large video/media files — already on live server
)

# Exact relative paths (forward-slash) to exclude
$ExcludeExactFiles = @(
    '.env',
    '.env.local',
    '.env.example',
    '.env.deploy.tmp',
    '.gitignore',
    '__deploy_ftp.ps1',
    '__build_deploy_zip.ps1',
    'Dockerfile.txt',
    'Dockerfile',
    'docker-compose.yml',
    '_dbtest.php',
    '__repair_product_images.php',
    '__seed_now.php',
    'seed_demo.php',
    'ping.php',
    'debug.txt',
    'whereami.txt',
    'check_web.ps1',
    'import-db.ps1',
    'import-db-patched.ps1',
    'run_update.ps1',
    'fresh_migrations.php',
    'run_migrations.php',
    'sdb-77_hosting_stackcp_net.sql',
    'COUPON_FIX_EXPLANATION.php'
)

$ExcludeExtensions = @('.log', '.jsonl', '.tmp', '.ps1', '.mp4', '.mov', '.avi', '.mkv', '.webm', '.flv', '.wmv')

# ── Build ZIP ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "══════════════════════════════════════════════════════"
Write-Host "  Cakeouflage Build & Deploy — $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Host "══════════════════════════════════════════════════════"
Write-Host ""

if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
    Write-Host "  Removed old $ZipName"
}

Write-Host "  Building ZIP..."
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipStream   = [System.IO.File]::Open($ZipPath, 'Create')
$archive     = New-Object System.IO.Compression.ZipArchive($zipStream, 'Create')

$allFiles = Get-ChildItem -Path $SourceDir -Recurse -File
$added    = 0
$skipped  = 0

foreach ($file in $allFiles) {
    $relPath     = $file.FullName.Substring($SourceDir.Length + 1)   # e.g.  vendor\composer\platform_check.php
    $relLower    = $relPath.ToLower()
    $relFwd      = $relPath.Replace('\', '/')                         # e.g.  vendor/composer/platform_check.php

    # ── Exclusion checks ──────────────────────────────────────────────────────
    $skip = $false

    foreach ($prefix in $ExcludePrefixLower) {
        if ($relLower.StartsWith($prefix)) { $skip = $true; break }
    }
    if ($skip) { $skipped++; continue }

    if ($ExcludeExactFiles -contains $relFwd) { $skipped++; continue }

    $ext = $file.Extension.ToLower()
    if ($ExcludeExtensions -contains $ext) { $skipped++; continue }

    # Skip this script, the ZIP being built, and the db dump
    if ($relFwd -eq '__build_deploy_zip.ps1')          { $skipped++; continue }
    if ($relFwd -eq 'cakeouflage_deploy_20260520.zip') { $skipped++; continue }
    if ($relFwd -eq 'cakeouflage_deploy.sql')          { $skipped++; continue }

    # ── Entry name in ZIP ─────────────────────────────────────────────────────
    $entryName = $relFwd
    if ($entryName -eq '.env.production') { $entryName = '.env' }   # rename for server

    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $entryName, 'Optimal') | Out-Null
    $added++
}

$archive.Dispose()
$zipStream.Dispose()

$sizeMB = [math]::Round((Get-Item $ZipPath).Length / 1MB, 2)
Write-Host "  ZIP built: $ZipName ($sizeMB MB, $added files added, $skipped skipped)"
Write-Host ""

# ── FTP upload helper ─────────────────────────────────────────────────────────
function Upload-FtpFile {
    param([string]$LocalPath, [string]$RemoteUrl)

    $fileInfo = Get-Item $LocalPath
    $req = [System.Net.FtpWebRequest][System.Net.WebRequest]::Create($RemoteUrl)
    $req.Method      = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $req.UsePassive   = $true
    $req.UseBinary    = $true
    $req.KeepAlive    = $false
    $req.EnableSsl    = $EnableSsl.IsPresent
    $req.ContentLength = $fileInfo.Length
    $req.Timeout      = 300000   # 5 min connect timeout
    $req.ReadWriteTimeout = 600000  # 10 min transfer timeout

    $fs = [System.IO.File]::OpenRead($LocalPath)
    $stream = $req.GetRequestStream()
    $buf = New-Object byte[] 65536
    while (($read = $fs.Read($buf, 0, $buf.Length)) -gt 0) {
        $stream.Write($buf, 0, $read)
    }
    $fs.Close()
    $stream.Close()

    $resp = $req.GetResponse()
    $resp.Close()
}

# Build remote base URL
$base = "ftp://$FtpHost/"
if ($RemoteBase -ne '') { $base += $RemoteBase.TrimEnd('/') + '/' }

# ── Upload 2 files ────────────────────────────────────────────────────────────
Write-Host "  Uploading to $base ..."
Write-Host ""

$filesToUpload = @(
    @{ Local = Join-Path $SourceDir "__extract_20260520.php"; Remote = $base + "__extract_20260520.php" },
    @{ Local = $ZipPath;                                      Remote = $base + $ZipName }
)

foreach ($f in $filesToUpload) {
    $fname   = Split-Path $f.Local -Leaf
    $sizekb  = [math]::Round((Get-Item $f.Local).Length / 1KB, 1)
    Write-Host "  Uploading $fname ($sizekb KB) ..."
    try {
        Upload-FtpFile -LocalPath $f.Local -RemoteUrl $f.Remote
        Write-Host "    OK"
    } catch {
        Write-Host "    FAIL: $($_.Exception.Message)"
        Write-Host "    Retrying..."
        Start-Sleep -Seconds 3
        try {
            Upload-FtpFile -LocalPath $f.Local -RemoteUrl $f.Remote
            Write-Host "    OK (retry)"
        } catch {
            Write-Host "    FAIL again: $($_.Exception.Message)"
        }
    }
}

Write-Host ""
Write-Host "══════════════════════════════════════════════════════"
Write-Host "  Upload complete!"
Write-Host "══════════════════════════════════════════════════════"
Write-Host ""
Write-Host "NEXT STEPS:"
Write-Host ""
Write-Host "  1. EXTRACT — open this URL in a browser:"
Write-Host "     https://cakeouflage.com/__extract_20260520.php?token=762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452"
Write-Host ""
Write-Host "  2. DB IMPORT — then open this URL:"
Write-Host "     https://cakeouflage.com/__db_import_20260520.php?token=762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452"
Write-Host ""
Write-Host "  3. CLEANUP — delete via StackCP File Manager:"
Write-Host "     __extract_20260520.php"
Write-Host "     cakeouflage_deploy_20260520.zip"
Write-Host "     __db_import_20260520.php"
Write-Host "     cakeouflage_deploy.sql"
Write-Host ""
