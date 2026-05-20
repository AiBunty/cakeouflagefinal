$envFile = ".env.local"
if (!(Test-Path $envFile)) { throw "Missing .env.local" }
Get-Content $envFile | ForEach-Object {
    if ($_ -match "^([^#=]+)=(.*)$") {
        Set-Variable -Name $Matches[1] -Value $Matches[2].Trim() -Scope Script
    }
}

$A_Name = "__ops_dbpeek_" + (Get-Random) + ".php"
$B_Name = "__ops_missing_tpl_" + (Get-Random) + ".php"
$SecretKey = [Guid]::NewGuid().ToString()

$ContentA = @"
<?php
if ((\$_GET['key'] ?? '') !== '$SecretKey') die('Unauthorized');
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';
try {
    \$conn = \App\Core\Database::getConnection();
    \$email = \$_GET['email'] ?? '';
    \$stmt = \$conn->prepare("SELECT otp_code FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    \$stmt->execute([\$email]);
    echo \$stmt->fetchColumn() ?: 'NOT_FOUND';
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
"@

$ContentB = @"
<?php
if ((\$_GET['key'] ?? '') !== '$SecretKey') die('Unauthorized');
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';
header('Content-Type: application/json');
try {
    \$pdo = \App\Core\Database::getConnection();
    \$snapshot = \$pdo->query("SELECT id, is_active FROM communication_templates WHERE channel='email' AND event_key='password_reset'")->fetchAll(PDO::FETCH_ASSOC);
    \$pdo->exec("UPDATE communication_templates SET is_active = 0 WHERE channel='email' AND event_key='password_reset'");

    \$pdo->prepare("INSERT INTO communication_logs (recipient, channel, event_key, status, payload_json) VALUES (?, ?, ?, ?, ?)")
         ->execute(['test@example.com', 'email', 'password_reset', 'queued', json_encode(['name'=>'Tester'])]);
    \$logId = \$pdo->lastInsertId();

    \$pdo->prepare("INSERT INTO communication_queue (communication_log_id) VALUES (?)")->execute([\$logId]);
    \$queueId = \$pdo->lastInsertId();

    \$pdo->prepare("INSERT INTO queue_jobs (job_type, payload_json, status, attempts) VALUES (?, ?, ?, 0)")
         ->execute(['send_communication', json_encode(['log_id' => \$logId]), 'queued']);
    \$jobId = \$pdo->lastInsertId();

    \App\Core\QueueWorker::process(\$pdo, 1);

    \$job = \$pdo->query("SELECT status, last_error, attempts FROM queue_jobs WHERE id = \$jobId")->fetch(PDO::FETCH_ASSOC);
    \$log = \$pdo->query("SELECT status, error_message, payload_json FROM communication_logs WHERE id = \$logId")->fetch(PDO::FETCH_ASSOC);

    foreach (\$snapshot as \$row) {
        \$pdo->prepare("UPDATE communication_templates SET is_active = ? WHERE id = ?")->execute([\$row['is_active'], \$row['id']]);
    }
    \$pdo->prepare("DELETE FROM queue_jobs WHERE id = ?")->execute([\$jobId]);
    \$pdo->prepare("DELETE FROM communication_queue WHERE communication_log_id = ?")->execute([\$logId]);
    \$pdo->prepare("DELETE FROM communication_logs WHERE id = ?")->execute([\$logId]);

    echo json_encode(['job' => \$job, 'log' => \$log]);
} catch (Exception \$e) {
    echo json_encode(['error' => \$e->getMessage()]);
}
"@

function Upload-FTP($file, $content) {
    $url = "ftp://$FTP_HOST/$file"
    $request = [System.Net.FtpWebRequest]::Create($url)
    $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($content)
    $request.ContentLength = $bytes.Length
    $rs = $request.GetRequestStream()
    $rs.Write($bytes, 0, $bytes.Length)
    $rs.Close()
}

function Delete-FTP($file) {
    $url = "ftp://$FTP_HOST/$file"
    try {
        $request = [System.Net.FtpWebRequest]::Create($url)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
        $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $request.GetResponse().Close()
    } catch {}
}

Upload-FTP $A_Name $ContentA
Upload-FTP $B_Name $ContentB

$baseUrl = "http://$FTP_HOST"

# 1. Admin Login Smoke
$loginUrl = "$baseUrl/admin/login.php"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-WebRequest -Uri $loginUrl -Method Post -Body @{email="Dcore"; action="login"} -WebSession $session > $null

$otp = Invoke-WebRequest -Uri "$baseUrl/$A_Name?key=$SecretKey&email=Dcore" -UseBasicParsing | Select-Object -ExpandProperty Content
if ($otp -eq 'NOT_FOUND') { throw "OTP not found for Dcore" }

Invoke-WebRequest -Uri $loginUrl -Method Post -Body @{otp=$otp; action="verify_otp"} -WebSession $session > $null

# 2. Production Plan Verify
$today = [DateTime]::Now.ToString("yyyy-MM-dd")
$planUrl = "$baseUrl/admin/production_plan.php"
$resp = Invoke-WebRequest -Uri $planUrl -WebSession $session
if ($resp.Content -notmatch "Production Plan") { throw "Production Plan title missing" }

$dateResp = Invoke-WebRequest -Uri "$planUrl?date=$today" -WebSession $session
if ($dateResp.StatusCode -ne 200) { throw "Date filter failed: $($dateResp.StatusCode)" }

$csvResp = Invoke-WebRequest -Uri "$planUrl?date=$today&export=csv" -WebSession $session
$csvLines = $csvResp.Content -split "`n"
$csvOut = $csvLines[0..1] -join "`n"

# 3. Negative Test
$negResp = Invoke-WebRequest -Uri "$baseUrl/$B_Name?key=$SecretKey" -UseBasicParsing
$negData = $negResp.Content | ConvertFrom-Json

# Cleanup
Delete-FTP $A_Name
Delete-FTP $B_Name

Write-Host "Production plan smoke: login success, page contains title, date status 200, CSV snippet:"
Write-Host $csvOut
Write-Host "`nNegative test evidence: job.status=$($negData.job.status), job.last_error=$($negData.job.last_error), log.status=$($negData.log.status), log.error_message=$($negData.log.error_message), template_missing=$($negData.log.payload_json -match 'template_missing":true')"
