$base = "https://cakeouflage.com"
$customer = @{
    name = "Parin Daulat"
    email = "parin11@gmail.com"
    phone = "+919330033000"
    password = "Parin@12345"
}
$adminEmail = "admin@cakeouflage.com"
$adminPasswords = @("admin123", "Admin123!", "cakeouflage123", "Password@123", "customer123", "Zebra@789")

$results = @{}

# 1. Customer Login/Register
$custSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
try {
    $loginBody = @{ email = $customer.email; password = $customer.password } | ConvertTo-Json
    $resp = Invoke-RestMethod -Uri "$base/api/auth/login" -Method Post -Body $loginBody -ContentType "application/json" -WebSession $custSession
    $results["CUSTOMER_LOGIN"] = "true"
} catch {
    try {
        $regBody = @{ name=$customer.name; email=$customer.email; phone=$customer.phone; password=$customer.password } | ConvertTo-Json
        $regResp = Invoke-RestMethod -Uri "$base/api/auth/register" -Method Post -Body $regBody -ContentType "application/json"
        $results["REGISTER_RESULT"] = "SUCCESS"
        try {
            $resp = Invoke-RestMethod -Uri "$base/api/auth/login" -Method Post -Body $loginBody -ContentType "application/json" -WebSession $custSession
            $results["CUSTOMER_LOGIN"] = "true"
        } catch {
            $results["CUSTOMER_LOGIN"] = "FAILED_AFTER_REG: $($_.Exception.Message)"
        }
    } catch {
        $results["REGISTER_RESULT"] = "FAILED: $($_.Exception.Message)"
        $results["CUSTOMER_LOGIN"] = "false"
    }
}

# 2. Send OTP
try {
    $otpSend = Invoke-RestMethod -Uri "$base/api/send-otp" -Method Post -Body (@{ email = $customer.email } | ConvertTo-Json) -ContentType "application/json"
    $results["OTP_SEND"] = $otpSend.message -replace "\r?\n", " "
} catch {
    $results["OTP_SEND"] = "ERR: $($_.Exception.Message)"
}

# 3. Verify OTP INVALID
try {
    $otpVerify = Invoke-RestMethod -Uri "$base/api/verify-otp" -Method Post -Body (@{ email = $customer.email; otp = "000000" } | ConvertTo-Json) -ContentType "application/json"
    $results["OTP_VERIFY_INVALID"] = "SUCCESS_UNEXPECTED"
} catch {
    # Expecting failure
    $results["OTP_VERIFY_INVALID"] = "CAUGHT_EXPECTED_ERROR: $($_.Exception.Message)"
}

# 4. Place Online Order
$onlineOrderId = "NOT_RUN"
if ($results["CUSTOMER_LOGIN"] -eq "true") {
    try {
        $products = Invoke-RestMethod -Uri "$base/api/catalog/products?limit=1" -Method Get
        $pid = $products.data.items[0].id
        if ($null -eq $pid) { throw "No products found" }
        
        $cart = Invoke-RestMethod -Uri "$base/api/cart/items" -Method Post -Body (@{ product_id = $pid; quantity = 1 } | ConvertTo-Json) -ContentType "application/json" -WebSession $custSession
        
        $orderBody = @{
            customer_name = $customer.name
            customer_email = $customer.email
            customer_phone = $customer.phone
            fulfilment_mode = "pickup"
            payment_method = "upi_manual"
        } | ConvertTo-Json
        $order = Invoke-RestMethod -Uri "$base/api/orders/place" -Method Post -Body $orderBody -ContentType "application/json" -WebSession $custSession
        $onlineOrderId = $order.data.order_number
        $results["ONLINE_ORDER"] = $onlineOrderId
    } catch {
        $results["ONLINE_ORDER"] = "ERR: $($_.Exception.Message)"
        $onlineOrderId = "ERROR"
    }
} else {
    $results["ONLINE_ORDER"] = "SKIPPED_NO_LOGIN"
}

# 5. Admin Login
$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminPassUsed = ""
foreach ($pw in $adminPasswords) {
    try {
        $aloginBody = @{ email = $adminEmail; password = $pw } | ConvertTo-Json
        $aresp = Invoke-RestMethod -Uri "$base/api/admin/auth/login" -Method Post -Body $aloginBody -ContentType "application/json" -WebSession $adminSession
        $adminPassUsed = $pw
        $results["ADMIN_LOGIN_PASSWORD"] = $pw
        break
    } catch {
        $results["ADMIN_LOGIN_PASSWORD"] = "FAILED_ALL"
    }
}

# 6. Admin Actions
if ($adminPassUsed -ne "") {
    # Order Visibility
    if ($onlineOrderId -ne "NOT_RUN" -and $onlineOrderId -notlike "ERR*") {
        try {
            $ao = Invoke-RestMethod -Uri "$base/api/admin/orders?q=$onlineOrderId" -Method Get -WebSession $adminSession
            $results["ADMIN_ORDER_VISIBLE"] = ($ao.data.items.Count -gt 0).ToString()
        } catch {
            $results["ADMIN_ORDER_VISIBLE"] = "ERR: $($_.Exception.Message)"
        }
    } else {
        $results["ADMIN_ORDER_VISIBLE"] = "SKIPPED"
    }

    # SMTP Roundtrip
    try {
        $smtp = Invoke-RestMethod -Uri "$base/api/admin/settings/smtp" -Method Get -WebSession $adminSession
        $payload = $smtp.data.settings
        if ($null -eq $payload) { $payload = @{} }
        $save = Invoke-RestMethod -Uri "$base/api/admin/settings/smtp" -Method Patch -Body ($payload | ConvertTo-Json -Compress) -ContentType "application/json" -WebSession $adminSession
        $results["SMTP_SAVE"] = $save.message
    } catch {
        $results["SMTP_SAVE"] = "ERR: $($_.Exception.Message)"
    }

    # WhatsApp Roundtrip
    try {
        $wa = Invoke-RestMethod -Uri "$base/api/admin/settings/whatsapp" -Method Get -WebSession $adminSession
        $payload2 = $wa.data.settings
        if ($null -eq $payload2) { $payload2 = @{} }
        $save2 = Invoke-RestMethod -Uri "$base/api/admin/settings/whatsapp" -Method Patch -Body ($payload2 | ConvertTo-Json -Compress) -ContentType "application/json" -WebSession $adminSession
        $results["WHATSAPP_SAVE"] = $save2.message
    } catch {
        $results["WHATSAPP_SAVE"] = "ERR: $($_.Exception.Message)"
    }

    # Media List
    try {
        $ml = Invoke-RestMethod -Uri "$base/api/admin/media" -Method Get -WebSession $adminSession
        $results["MEDIA_LIST_COUNT"] = $ml.data.items.Count
    } catch {
        $results["MEDIA_LIST_COUNT"] = "ERR: $($_.Exception.Message)"
    }
}

# Final Output
foreach ($k in $results.Keys) {
    Write-Host "$k=$($results[$k])"
}
