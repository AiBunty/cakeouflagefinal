$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginUrl = "http://localhost:8888/admin/login.php"
$email = "aibuntysystems@gmail.com"

Write-Host "Sending OTP..."
$res1 = Invoke-WebRequest -Uri $loginUrl -Method Post -Body @{action="send_otp"; login=$email} -WebSession $session
Write-Host "Send OTP Status: $($res1.StatusCode)"

$otp = docker exec -i cakeouflage_mysql mysql -N -uroot -prootpassword cakeouflage_dev -e "SELECT otp FROM otp_verifications WHERE email='$email' ORDER BY id DESC LIMIT 1;"
$otp = $otp.Trim()
Write-Host "Retrieved OTP: $otp"

Write-Host "Verifying OTP..."
$res2 = Invoke-WebRequest -Uri $loginUrl -Method Post -Body @{action="verify_otp"; login=$email; otp=$otp} -WebSession $session
Write-Host "Verify OTP Status: $($res2.StatusCode)"

Write-Host "Fetching Communications page..."
$commUrl = "http://localhost:8888/admin/communications.php"
try {
    $res3 = Invoke-WebRequest -Uri $commUrl -Method Get -WebSession $session
    $content = $res3.Content
    $status = $res3.StatusCode
    
    $hasTemplates = $content -match "Communication Templates"
    $hasQuill = $content -match "quillEditor"
    $hasCustom = $content -match "customValuesPanel"
    
    Write-Host "Status Code: $status"
    Write-Host "Contains 'Communication Templates': $hasTemplates"
    Write-Host "Contains 'quillEditor': $hasQuill"
    Write-Host "Contains 'customValuesPanel': $hasCustom"
} catch {
    Write-Host "Failed to fetch communications page: $_"
}
