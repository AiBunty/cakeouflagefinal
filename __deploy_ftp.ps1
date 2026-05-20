<#
.SYNOPSIS
    __deploy_ftp.ps1 — Full fresh FTP upload for cakeouflage.com production deployment.

.DESCRIPTION
    Uploads the entire codebase to the live server via FTP, replacing all files.
    - Reads FTP credentials from .env.local
    - Uploads .env.production AS .env (overwrites server .env with production values)
    - Skips: .git/, _live_mirrors/, node_modules/, .env (dev), .env.local, .env.example, *.log
    - Includes: vendor/, uploads/products/, cakeouflage_deploy.sql, all source files

.USAGE
    cd "D:\GITHUB Projects\Cakeouflage E-commerce\cakeouflage-ecommerce"
    .\__deploy_ftp.ps1

.NOTES
    Remote base path defaults to "public_html/" — if your FTP root IS the web root, set:
        $RemoteBase = ""
    If the server uses FTPS (FTP-over-TLS), set:
        $EnableSsl = $true
#>

param(
    [string]$RemoteBase = $null,   # Production site root on FTP
    [bool]$EnableSsl    = $false            # Set $true for FTPS (explicit TLS)
)

$ErrorActionPreference = 'Continue'
Set-StrictMode -Off

# ─── Paths ────────────────────────────────────────────────────────────────
$LocalRoot    = Join-Path $PSScriptRoot "public_html/cakeouflage.com"
$EnvLocalFile = Join-Path $PSScriptRoot ".env.local"

# ─── Read FTP credentials from .env.local ────────────────────────────────
if (-not (Test-Path $EnvLocalFile)) {
    Write-Error ".env.local not found. Cannot read FTP credentials."
    exit 1
}

$Env = @{}
Get-Content $EnvLocalFile | ForEach-Object {
    if ($_ -match '^([A-Z_]+)=(.+)$') {
        $Env[$Matches[1]] = $Matches[2].Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($RemoteBase)) {
    $RemoteBase = $Env['FTP_REMOTE_PATH_LIVE']
}

if ([string]::IsNullOrWhiteSpace($RemoteBase)) {
    $RemoteBase = $Env['FTP_REMOTE_PATH']
}

if ([string]::IsNullOrWhiteSpace($RemoteBase)) {
    $RemoteBase = 'public_html/cakeouflage.com/'
}

$RemoteBase = $RemoteBase.Trim().Replace('\', '/')
if ($RemoteBase.StartsWith('/')) {
    $RemoteBase = 'public_html/' + $RemoteBase.TrimStart('/')
}
if ($RemoteBase -ne '' -and -not $RemoteBase.EndsWith('/')) {
    $RemoteBase += '/'
}

$FtpHost = $Env['FTP_HOST_LIVE']
if ([string]::IsNullOrWhiteSpace($FtpHost)) { $FtpHost = $Env['FTP_HOST'] }

$FtpUser = $Env['FTP_USER_LIVE']
if ([string]::IsNullOrWhiteSpace($FtpUser)) { $FtpUser = $Env['FTP_USER'] }

$FtpPass = $Env['FTP_PASS_LIVE']
if ([string]::IsNullOrWhiteSpace($FtpPass)) { $FtpPass = $Env['FTP_PASS'] }

if (-not $FtpHost -or -not $FtpUser -or -not $FtpPass) {
    Write-Error "FTP_HOST, FTP_USER, or FTP_PASS missing from .env.local"
    exit 1
}

$FtpBase   = "ftp://$FtpHost/$RemoteBase"
$Cred      = [System.Net.NetworkCredential]::new($FtpUser, $FtpPass)

Write-Host ""
Write-Host "══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Cakeouflage FTP Deploy — $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Cyan
Write-Host "══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Local : $LocalRoot"
Write-Host "  Remote: $FtpBase"
Write-Host "  User  : $FtpUser"
Write-Host ""
Write-Host "  Base  : $RemoteBase"
Write-Host ""

# ─── Exclusion rules ─────────────────────────────────────────────────────
# Directories whose RELATIVE path starts with one of these prefixes are skipped
$ExcludeDirPrefixes = @(
    '.git',
    '_live_mirrors',
    'node_modules',
    'storage\cache',
    'storage\logs',
    'storage\import-backups',
    'storage\import-test',
    'docs',
    'client'           # front-end source (built assets are in public/ or served separately)
)

# Exact relative file names to skip (relative to $LocalRoot, forward-slash)
$ExcludeExactFiles = @(
    '.env.local',
    '.env.example',
    '.env.deploy.tmp',
    '__deploy_ftp.ps1',
    '.gitignore',
    'Dockerfile.txt',
    '_dbtest.php',
    '__repair_product_images.php',
    '__seed_now.php',
    'seed_demo.php',
    'live_template_queue_probe_20260518.php',
    'run_update.ps1',
    'whereami.txt',
    'db_probe.php',
    'db_check_20260520.php',
    '__db_import_20260520.php',
    '__extract_20260520.php'
)

# File extensions to skip
$ExcludeExtensions = @('.log', '.md', '.jsonl', '.tmp')

# ─── Collect files to upload ─────────────────────────────────────────────
Write-Host "Scanning files…" -ForegroundColor Yellow

$AllFiles   = Get-ChildItem -Path $LocalRoot -Recurse -File
$ToUpload   = [System.Collections.Generic.List[hashtable]]::new()
$SkipCount  = 0

foreach ($File in $AllFiles) {
    $Rel       = $File.FullName.Substring($LocalRoot.Length).TrimStart('\')
    $RelFwd    = $Rel.Replace('\', '/')
    $RelLower  = $Rel.ToLower()

    # Check excluded directories
    $InExcludedDir = $false
    foreach ($Prefix in $ExcludeDirPrefixes) {
        if ($RelLower.StartsWith($Prefix.ToLower() + '\') -or $RelLower.StartsWith($Prefix.ToLower() + '/')) {
            $InExcludedDir = $true
            break
        }
    }
    if ($InExcludedDir) { $SkipCount++; continue }

    # Check excluded exact files
    if ($ExcludeExactFiles -contains $RelFwd) { $SkipCount++; continue }

    # Check excluded extensions
    if ($ExcludeExtensions -contains $File.Extension.ToLower()) { $SkipCount++; continue }

    # Special: .env.production → upload as .env
    $RemoteName = $RelFwd
    if ($RelFwd -eq '.env.production') {
        $RemoteName = '.env'
        Write-Host "  [RENAME] .env.production → .env" -ForegroundColor Magenta
    }

    $ToUpload.Add(@{
        LocalPath  = $File.FullName
        RemotePath = $RemoteName
    })
}

Write-Host "  Files to upload : $($ToUpload.Count)"
Write-Host "  Files skipped   : $SkipCount"
Write-Host ""

# ─── Helpers ──────────────────────────────────────────────────────────────

function Invoke-FtpRequest {
    param(
        [string]$Url,
        [string]$Method,
        [System.Net.NetworkCredential]$Credential,
        [bool]$Ssl,
        [byte[]]$Body = $null
    )
    try {
        $req                       = [System.Net.FtpWebRequest]::Create($Url)
        $req.Method                = $Method
        $req.Credentials           = $Credential
        $req.EnableSsl             = $Ssl
        $req.UseBinary             = $true
        $req.UsePassive            = $true
        $req.KeepAlive             = $true
        $req.Timeout               = 120000   # 2 min per request
        $req.ReadWriteTimeout      = 120000

        if ($null -ne $Body) {
            $req.ContentLength = $Body.Length
            $stream = $req.GetRequestStream()
            $stream.Write($Body, 0, $Body.Length)
            $stream.Close()
        }

        $resp = $req.GetResponse()
        $resp.Dispose()
        return $true
    } catch {
        return $false
    }
}

# Track which remote directories have been ensured (to avoid redundant MKD calls)
$CreatedDirs = [System.Collections.Generic.HashSet[string]]::new()

function Ensure-RemoteDir {
    param([string]$DirUrl)
    if ($CreatedDirs.Contains($DirUrl)) { return }
    [void]$CreatedDirs.Add($DirUrl)
    # Try MKD — silently ignore if dir already exists
    Invoke-FtpRequest -Url $DirUrl -Method "MKD" -Credential $Cred -Ssl $EnableSsl | Out-Null
}

function Upload-File {
    param([string]$LocalPath, [string]$RemoteUrl)
    $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
    return Invoke-FtpRequest -Url $RemoteUrl -Method "STOR" -Credential $Cred -Ssl $EnableSsl -Body $bytes
}

# Ensure the remote base dir itself exists
Ensure-RemoteDir -DirUrl $FtpBase

# ─── Upload loop ─────────────────────────────────────────────────────────
Write-Host "Starting upload…" -ForegroundColor Yellow
Write-Host ""

$UploadOk   = 0
$UploadFail = 0
$Total      = $ToUpload.Count
$Index      = 0

foreach ($Item in $ToUpload) {
    $Index++
    $LocalPath  = $Item.LocalPath
    $RemotePath = $Item.RemotePath

    # Build full remote URL
    $RemoteUrl  = $FtpBase.TrimEnd('/') + '/' + $RemotePath.TrimStart('/')

    # Ensure parent directory exists
    $RemoteDir = $RemoteUrl.Substring(0, $RemoteUrl.LastIndexOf('/') + 1)
    if ($RemoteDir -ne $FtpBase) {
        # Recursively ensure all ancestor dirs exist
        $Parts = $RemotePath.Split('/')
        $DirAccum = $FtpBase.TrimEnd('/')
        for ($d = 0; $d -lt $Parts.Count - 1; $d++) {
            $DirAccum += '/' + $Parts[$d]
            Ensure-RemoteDir -DirUrl ($DirAccum + '/')
        }
    }

    # Upload
    $Pct      = [math]::Round($Index / $Total * 100)
    $FileName = Split-Path $LocalPath -Leaf
    Write-Host ("  [{0,3}%] [{1}/{2}] {3}" -f $Pct, $Index, $Total, $RemotePath) -ForegroundColor Gray

    $ok = Upload-File -LocalPath $LocalPath -RemoteUrl $RemoteUrl
    if ($ok) {
        $UploadOk++
    } else {
        $UploadFail++
        Write-Host "  [FAIL] $RemotePath" -ForegroundColor Red
        # Retry once
        Start-Sleep -Milliseconds 500
        $ok = Upload-File -LocalPath $LocalPath -RemoteUrl $RemoteUrl
        if ($ok) {
            $UploadOk++
            $UploadFail--
            Write-Host "  [RETRY OK] $RemotePath" -ForegroundColor Yellow
        }
    }
}

# ─── Summary ──────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  UPLOAD COMPLETE" -ForegroundColor Cyan
Write-Host "══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Uploaded  : $UploadOk"
Write-Host "  Failed    : $UploadFail"
Write-Host "  Skipped   : $SkipCount"
Write-Host ""

if ($UploadFail -gt 0) {
    Write-Host "  ✗ $UploadFail file(s) failed — check connection and retry." -ForegroundColor Red
} else {
    Write-Host "  ✓ All files uploaded successfully." -ForegroundColor Green
}

Write-Host ""
Write-Host "  NEXT STEPS:" -ForegroundColor Yellow
Write-Host "  1. Verify PHP works: https://cakeouflage.com/api/health"
Write-Host "  2. Run DB import  : https://cakeouflage.com/__db_import_20260520.php?token=762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452"
Write-Host "  3. After import   : DELETE __db_import_20260520.php and cakeouflage_deploy.sql from server"
Write-Host "  4. Smoke test     : /shop, /admin/import-products.php, /api/health/db"
Write-Host ""
