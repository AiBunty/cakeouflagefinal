<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

require_once __DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    public static function sendOtp($email, $otp, $customerName = 'Customer')
    {
        $safeName = trim((string)$customerName);
        if ($safeName === '') {
            $safeName = 'Customer';
        }

        $subject = 'Your OTP Code';
        $textBody = "Hello {$safeName},\n\nYour OTP is: {$otp}\nThis OTP is valid for 5 minutes.\n\nIf you did not request this OTP, please ignore this email.";
        $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#1f2937;">'
            . '<p>Hello ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Your OTP is: <strong>' . htmlspecialchars((string)$otp, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>This OTP is valid for 5 minutes.</p>'
            . '<p>If you did not request this OTP, please ignore this email.</p>'
            . '</div>';

        // Prefer SMTP settings saved from admin panel.
        try {
            $pdo = Database::getConnection();
            if ($pdo) {
                $transport = SmtpTransportService::fromDatabase($pdo);
                if ($transport->isConfigured()) {
                    $transport->send([(string)$email], $subject, $textBody, $htmlBody);
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('OTP SMTP DB transport failed: ' . $e->getMessage());
        }

        $mail = new PHPMailer(true);
        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host = Env::get('SMTP_HOST_LIVE', 'smtp.dcoresystems.com');
            $mail->SMTPAuth = true;
            $mail->Username = Env::get('SMTP_USER_LIVE', 'noreply@dcoresystems.com');
            $mail->Password = Env::get('SMTP_PASS_LIVE', '') ?: '';
            $mail->SMTPSecure = Env::get('SMTP_SECURE_LIVE', 'ssl');
            $mail->Port = (int)(Env::get('SMTP_PORT_LIVE', '465') ?: 465);
            $mail->setFrom(Env::get('SMTP_FROM_EMAIL_LIVE', 'noreply@dcoresystems.com') ?: 'noreply@dcoresystems.com', Env::get('SMTP_FROM_NAME_LIVE', 'Cakeouflage') ?: 'Cakeouflage');
            $mail->addAddress($email);
            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function sendOrderPlacedEmail(
$email,
$customerName,
$orderId,
$itemName,
$amount,
$phone,
$itemDetails = []
) {
$mail = new PHPMailer(true);

try {

    // SMTP
$mail->isSMTP();

  $mail->Host = Env::get('SMTP_HOST_LIVE', 'smtp.dcoresystems.com');
$mail->SMTPAuth = true;

$mail->Username = Env::get('SMTP_USER_LIVE', 'noreply@dcoresystems.com');
$mail->Password = Env::get('SMTP_PASS_LIVE', '') ?: '';

$mail->SMTPSecure = Env::get('SMTP_SECURE_LIVE', 'ssl');
$mail->Port = (int)(Env::get('SMTP_PORT_LIVE', '465') ?: 465);

$mail->setFrom(
    Env::get('SMTP_FROM_EMAIL_LIVE', 'noreply@dcoresystems.com') ?: 'noreply@dcoresystems.com',
    Env::get('SMTP_FROM_NAME_LIVE', 'Cakeouflage') ?: 'Cakeouflage'
);

    // receiver
    $mail->addAddress($email);

    // HTML mail
    $mail->isHTML(true);

    $mail->Subject = "Cakeouflage Order Received - $orderId";

    $mail->Body = "
    <div style='background:#f5eef2;padding:40px;font-family:Arial,sans-serif;'>

        <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

       <div style='background:#80001F;padding:28px;color:#fff;'>

    <div style='margin-bottom:12px;'>

        <img 
            src='https://i.ibb.co/hRytXC3F/whitelogo.png'
            alt='Cakeouflage Logo'
            style='height:100px; display:block;'
        >

    </div>

    <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
        Luxury Cakes • Crafted for Celebrations
    </p>

</div>

            <div style='padding:40px;'>

                <h2 style='margin-top:0;color:#1d1115;font-size:30px;'>
                    Hi $customerName 👋
                </h2>

                <p style='color:#5f4c55;font-size:16px;line-height:1.8;'>
                    Thank you for placing your order with Cakeouflage.
                    Your payment verification is currently pending.
                </p>

                <div style='margin-top:30px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;'>

                    <h3 style='margin-top:0;color:#80001F;font-size:22px;'>
                        🧾 Order Summary
                    </h3>

                    <p><strong>Order ID:</strong> $orderId</p>
                    <p><strong>Customer:</strong> $customerName</p>
                    <p><strong>Mobile:</strong> $phone</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Item:</strong> $itemName</p>
";

    // Render per-item details table if provided
    if (!empty($itemDetails)) {
        $mail->Body .= "
                    <table style='width:100%;border-collapse:collapse;margin-top:12px;font-size:13px;'>
                        <thead>
                            <tr style='background:#f5eef2;'>
                                <th style='padding:8px;text-align:left;border-bottom:1px solid #ebd4dc;'>Item</th>
                                <th style='padding:8px;text-align:left;border-bottom:1px solid #ebd4dc;'>Variant</th>
                                <th style='padding:8px;text-align:left;border-bottom:1px solid #ebd4dc;'>Note / Topper</th>
                                <th style='padding:8px;text-align:right;border-bottom:1px solid #ebd4dc;'>Qty × Price</th>
                            </tr>
                        </thead>
                        <tbody>
";
        foreach ($itemDetails as $itm) {
            $note = htmlspecialchars((string)($itm['cake_message'] ?? ''), ENT_QUOTES, 'UTF-8');
            $topper = htmlspecialchars((string)($itm['topper_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8');
            $noteTopper = array_filter([$note ? "🎂 $note" : '', $topper && $topper !== 'No Topper' ? "🎀 $topper" : '']);
            $noteTopperStr = implode('<br>', $noteTopper) ?: '—';
            $qty  = (int)($itm['quantity'] ?? 1);
            $price = '₹' . number_format((float)($itm['line_total'] ?? 0), 0);
            $mail->Body .= "
                            <tr>
                                <td style='padding:7px;border-bottom:1px solid #f5e8ec;'>" . htmlspecialchars((string)($itm['product_name_snapshot'] ?? $itm['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>
                                <td style='padding:7px;border-bottom:1px solid #f5e8ec;'>" . htmlspecialchars((string)($itm['variant_snapshot'] ?? $itm['variant_label'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>
                                <td style='padding:7px;border-bottom:1px solid #f5e8ec;font-size:12px;color:#7a5060;'>$noteTopperStr</td>
                                <td style='padding:7px;border-bottom:1px solid #f5e8ec;text-align:right;'>{$qty}×$price</td>
                            </tr>
";
        }
        $mail->Body .= "
                        </tbody>
                    </table>
";
    }

    $mail->Body .= "
                    <p style='font-size:24px;color:#80001F;font-weight:bold;'>
                        Amount: ₹$amount
                    </p>

                </div>

                <div style='margin-top:25px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;'>
                    Your payment confirmation is still pending.
                    Once verified, you will receive a confirmation email shortly.
                </div>

            </div>

            <div style='background:#140b0f;padding:30px;color:#fff;'>

                <h3 style='margin-top:0;font-family:Georgia,serif;'>
                    Team Cakeouflage
                </h3>

                <p style='color:#d7c6cc;font-size:14px;'>
                    Premium Designer Cakes crafted with elegance and creativity.
                </p>

                <p style='color:#d7c6cc;font-size:14px;'>
                    🌐 www.cakeouflage.com
                </p>

            </div>

        </div>

    </div>
    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Order Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}
public static function sendPaymentConfirmedEmail(
$email,
$customerName,
$orderId,
$itemName,
$amount,
$phone
) {

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host = Env::get('SMTP_HOST_LIVE', 'smtp.dcoresystems.com');
    $mail->SMTPAuth = true;
    $mail->Username = Env::get('SMTP_USER_LIVE', 'noreply@dcoresystems.com');
    $mail->Password = Env::get('SMTP_PASS_LIVE', '') ?: '';
    $mail->SMTPSecure = Env::get('SMTP_SECURE_LIVE', 'ssl');
    $mail->Port = (int)(Env::get('SMTP_PORT_LIVE', '465') ?: 465);

    $mail->setFrom(
    Env::get('SMTP_FROM_EMAIL_LIVE', 'noreply@dcoresystems.com') ?: 'noreply@dcoresystems.com',
    Env::get('SMTP_FROM_NAME_LIVE', 'Cakeouflage') ?: 'Cakeouflage'
);

    $mail->addAddress($email);

    $mail->isHTML(true);

    $mail->Subject = "Payment Confirmed - $orderId";

    $mail->Body = "
    <div style='background:#eef8f1;padding:40px;font-family:Arial,sans-serif;'>

        <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

           <div style='background:#166534;padding:28px;color:#fff;'>

    <div style='margin-bottom:12px;'>

        <img 
            src='https://i.ibb.co/hRytXC3F/whitelogo.png'
            alt='Cakeouflage Logo'
            style='height:100px; display:block;'
        >

    </div>

    <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
        Payment Confirmed Successfully
    </p>

</div>

            <div style='padding:40px;'>

                <h2 style='margin-top:0;color:#111827;font-size:30px;'>
                    Hi $customerName 👋
                </h2>

                <p style='color:#4b5563;font-size:16px;line-height:1.8;'>
                    We have successfully received your payment.
                    Your order is now confirmed and our team has started preparing your delicious cake.
                </p>

                <div style='margin-top:30px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;'>

                    <h3 style='margin-top:0;color:#166534;font-size:22px;'>
                        ✅ Confirmed Order
                    </h3>

                    <p><strong>Order ID:</strong> $orderId</p>
                    <p><strong>Customer:</strong> $customerName</p>
                    <p><strong>Mobile:</strong> $phone</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Item:</strong> $itemName</p>

                    <p style='font-size:24px;color:#166534;font-weight:bold;'>
                        Amount Paid: ₹$amount
                    </p>

                </div>

                <div style='margin-top:25px;background:#dcfce7;padding:18px;border-radius:12px;color:#166534;font-weight:bold;'>
                    Your order has been confirmed successfully 🎉
                </div>

            </div>

            <div style='background:#052e16;padding:30px;color:#fff;'>

                <h3 style='margin-top:0;font-family:Georgia,serif;'>
                    Team Cakeouflage
                </h3>

                <p style='color:#d1fae5;font-size:14px;'>
                    Premium cakes crafted specially for your celebration.
                </p>

                <p style='color:#d1fae5;font-size:14px;'>
                    🌐 www.cakeouflage.com
                </p>

            </div>

        </div>

    </div>
    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Payment Confirm Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}

public static function sendOrderRejectedEmail(
$email,
$customerName,
$orderId,
$itemName,
$amount,
$phone
) {

$mail = new PHPMailer(true);

try {

 $mail->isSMTP();

  $mail->Host = 'smtp.dcoresystems.com';
$mail->SMTPAuth = true;

$mail->Username = 'noreply@dcoresystems.com';
$mail->Password = 'Zebra@789';

$mail->SMTPSecure = 'ssl';
$mail->Port = 465;

$mail->setFrom(
    'noreply@dcoresystems.com',
    'Cakeouflage'
);

    $mail->addAddress($email);

    $mail->isHTML(true);

    $mail->Subject = "Order Rejected - $orderId";

    $mail->Body = "
    <div style='background:#fff1f2;padding:40px;font-family:Arial,sans-serif;'>

        <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

            <div style='background:#991b1b;padding:28px;color:#fff;'>

                <div style='margin-bottom:12px;'>

                    <img 
                        src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                        alt='Cakeouflage Logo'
                        style='height:100px; display:block;'
                    >

                </div>

                <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
                    Order Rejected
                </p>

            </div>

            <div style='padding:40px;'>

                <h2 style='margin-top:0;color:#111827;font-size:30px;'>
                    Hi $customerName
                </h2>

                <p style='color:#4b5563;font-size:16px;line-height:1.8;'>
                    We could not verify your payment successfully.
                    Therefore your order has been rejected.
                </p>

                <div style='margin-top:30px;background:#fef2f2;border:1px solid #fecaca;border-radius:16px;padding:24px;'>

                    <h3 style='margin-top:0;color:#991b1b;font-size:22px;'>
                        ❌ Rejected Order
                    </h3>

                    <p><strong>Order ID:</strong> $orderId</p>
                    <p><strong>Customer:</strong> $customerName</p>
                    <p><strong>Mobile:</strong> $phone</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Item:</strong> $itemName</p>

                    <p style='font-size:24px;color:#991b1b;font-weight:bold;'>
                        Amount: ₹$amount
                    </p>

                </div>

                <div style='margin-top:25px;background:#fee2e2;padding:18px;border-radius:12px;color:#991b1b;font-weight:bold;'>
                    Since payment was not received, please place your order again.
                </div>

                <div style='margin-top:30px;'>
                    <a 
                        href='https://cakeouflage.com'
                        style='background:#991b1b;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;'
                    >
                        Place Order Again
                    </a>
                </div>

            </div>

            <div style='background:#450a0a;padding:30px;color:#fff;'>

                <h3 style='margin-top:0;font-family:Georgia,serif;'>
                    Team Cakeouflage
                </h3>

                <p style='color:#fecaca;font-size:14px;'>
                    Premium cakes crafted specially for your celebration.
                </p>

                <p style='color:#fecaca;font-size:14px;'>
                    🌐 www.cakeouflage.com
                </p>

            </div>

        </div>

    </div>
    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Rejected Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}
public static function sendOrderReadyEmail(
$email,
$customerName,
$orderId,
$itemName,
$amount,
$phone
) {

$mail = new PHPMailer(true);

try {

$mail->isSMTP();

  $mail->Host = 'smtp.dcoresystems.com';
$mail->SMTPAuth = true;

$mail->Username = 'noreply@dcoresystems.com';
$mail->Password = 'Zebra@789';

$mail->SMTPSecure = 'ssl';
$mail->Port = 465;

$mail->setFrom(
    'noreply@dcoresystems.com',
    'Cakeouflage'
);

    $mail->addAddress($email);

    $mail->isHTML(true);

    $mail->Subject = "Your Order is Ready - $orderId";

    $mail->Body = "
    <div style='background:#eff6ff;padding:40px;font-family:Arial,sans-serif;'>

        <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

            <div style='background:#1d4ed8;padding:28px;color:#fff;'>

                <div style='margin-bottom:12px;'>

                    <img 
                        src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                        alt='Cakeouflage Logo'
                        style='height:100px; display:block;'
                    >

                </div>

                <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
                    Your Order is Ready 
                </p>

            </div>

            <div style='padding:40px;'>

                <h2 style='margin-top:0;color:#111827;font-size:30px;'>
                    Hi $customerName 
                </h2>

                <p style='color:#4b5563;font-size:16px;line-height:1.8;'>
                    Great news! Your delicious Cakeouflage order is now ready.
                </p>

                <div style='margin-top:30px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;'>

                    <h3 style='margin-top:0;color:#1d4ed8;font-size:22px;'>
                         Ready Order
                    </h3>

                    <p><strong>Order ID:</strong> $orderId</p>
                    <p><strong>Customer:</strong> $customerName</p>
                    <p><strong>Mobile:</strong> $phone</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Item:</strong> $itemName</p>

                    <p style='font-size:24px;color:#1d4ed8;font-weight:bold;'>
                        Amount: ₹$amount
                    </p>

                </div>

                <div style='margin-top:25px;background:#dbeafe;padding:18px;border-radius:12px;color:#1d4ed8;font-weight:bold;'>
                    Your order is ready for pickup/delivery 
                </div>

            </div>

            <div style='background:#172554;padding:30px;color:#fff;'>

                <h3 style='margin-top:0;font-family:Georgia,serif;'>
                    Team Cakeouflage
                </h3>

                <p style='color:#bfdbfe;font-size:14px;'>
                    Premium cakes crafted specially for your celebration.
                </p>

                <p style='color:#bfdbfe;font-size:14px;'>
                     www.cakeouflage.com
                </p>

            </div>

        </div>

    </div>
    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Ready Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}
public static function sendAdminOrderPlacedEmail(
    string $orderNumber,
    string $customerName,
    string $customerPhone,
    string $itemNames,
    float $amount
) {

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host = 'smtp.dcoresystems.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'noreply@dcoresystems.com';
    $mail->Password = 'Zebra@789';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('noreply@dcoresystems.com', 'Cakeouflage');

    // ✅ ADMIN EMAILA
  $mail->addAddress('cakeouflage@gmail.com');

    $mail->isHTML(true);

    $mail->Subject = "🛒 New Order Received - $orderNumber";

    $mail->Body = "

    <div style='background:#f5eef2;padding:40px;font-family:Arial,sans-serif;'>

    <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

        <div style='background:#80001F;padding:28px;color:#fff;'>

            <div style='margin-bottom:12px;'>

                <img 
                    src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                    alt='Cakeouflage Logo'
                    style='height:100px; display:block;'
                >

            </div>

            <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
                New Order Notification
            </p>

        </div>

        <div style='padding:40px;'>

            <h2 style='margin-top:0;color:#1d1115;font-size:30px;'>
                🛒 New Order Received
            </h2>

            <p style='color:#5f4c55;font-size:16px;line-height:1.8;'>
                A new customer has placed an order on Cakeouflage.
            </p>

            <div style='margin-top:30px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;'>

                <h3 style='margin-top:0;color:#80001F;font-size:22px;'>
                    📦 Order Details
                </h3>

                <p><strong>Order ID:</strong> {$orderNumber}</p>

                <p><strong>Customer Name:</strong> {$customerName}</p>

                <p><strong>Phone:</strong> {$customerPhone}</p>

                <p><strong>Items:</strong> {$itemNames}</p>

                <p style='font-size:24px;color:#80001F;font-weight:bold;'>
                    Amount: ₹{$amount}
                </p>

            </div>

        </div>

        <div style='background:#140b0f;padding:30px;color:#fff;'>

            <h3 style='margin-top:0;font-family:Georgia,serif;'>
                Team Cakeouflage
            </h3>

            <p style='color:#d7c6cc;font-size:14px;'>
                Premium Designer Cakes crafted with elegance and creativity.
            </p>

            <p style='color:#d7c6cc;font-size:14px;'>
                🌐 www.cakeouflage.com
            </p>

        </div>

    </div>

</div>



    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Admin Order Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}

public static function sendAdminPaymentConfirmedEmail(
    string $orderNumber,
    string $customerName,
    string $customerPhone,
    string $itemNames,
    float $amount
) {

$mail = new PHPMailer(true);

try {

  $mail->isSMTP();

  $mail->Host = 'smtp.dcoresystems.com';
$mail->SMTPAuth = true;

$mail->Username = 'noreply@dcoresystems.com';
$mail->Password = 'Zebra@789';

$mail->SMTPSecure = 'ssl';
$mail->Port = 465;

$mail->setFrom(
    'noreply@dcoresystems.com',
    'Cakeouflage'
);

    // ✅ ADMIN EMAIL
   $mail->addAddress('cakeouflage@gmail.com');

    $mail->isHTML(true);

    $mail->Subject = "✅ Payment Confirmed - $orderNumber";

    $mail->Body = "

    <div style='background:#f5eef2;padding:40px;font-family:Arial,sans-serif;'>

    <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

        <div style='background:#166534;padding:28px;color:#fff;'>

            <div style='margin-bottom:12px;'>

                <img 
                    src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                    alt='Cakeouflage Logo'
                    style='height:100px; display:block;'
                >

            </div>

           <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
    Payment Confirmed
</p>

        </div>

        <div style='padding:40px;'>

            <h2 style='margin-top:0;color:#1d1115;font-size:30px;'>
    ✅ Payment Confirmed
</h2>

<p style='color:#5f4c55;font-size:16px;line-height:1.8;'>
    Customer payment has been verified successfully.
</p>

            <div style='margin-top:30px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;'>

                <h3 style='margin-top:0;color:#80001F;font-size:22px;'>
                    📦 Order Details
                </h3>

                <p><strong>Order ID:</strong> {$orderNumber}</p>

                <p><strong>Customer Name:</strong> {$customerName}</p>

                <p><strong>Phone:</strong> {$customerPhone}</p>

                <p><strong>Items:</strong> {$itemNames}</p>

                <p style='font-size:24px;color:#80001F;font-weight:bold;'>
                    Amount: ₹{$amount}
                </p>

            </div>

        </div>

        <div style='background:#140b0f;padding:30px;color:#fff;'>

            <h3 style='margin-top:0;font-family:Georgia,serif;'>
                Team Cakeouflage
            </h3>

            <p style='color:#d7c6cc;font-size:14px;'>
                Premium Designer Cakes crafted with elegance and creativity.
            </p>

            <p style='color:#d7c6cc;font-size:14px;'>
                🌐 www.cakeouflage.com
            </p>

        </div>

    </div>

</div>



    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Admin Order Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}
public static function sendAdminOrderReadyEmail(
    string $orderNumber,
    string $customerName,
    string $customerPhone,
    string $itemNames,
    float $amount
) {

$mail = new PHPMailer(true);

try {
$mail->isSMTP();

  $mail->Host = 'smtp.dcoresystems.com';
$mail->SMTPAuth = true;

$mail->Username = 'noreply@dcoresystems.com';
$mail->Password = 'Zebra@789';

$mail->SMTPSecure = 'ssl';
$mail->Port = 465;

$mail->setFrom(
    'noreply@dcoresystems.com',
    'Cakeouflage'
);

    // ✅ ADMIN EMAIL
   $mail->addAddress('cakeouflage@gmail.com');

    $mail->isHTML(true);

    $mail->Subject = "🎂 Order Ready - $orderNumber";

    $mail->Body = "

    <div style='background:#f5eef2;padding:40px;font-family:Arial,sans-serif;'>

    <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

        <div style='background:#80001F;padding:28px;color:#fff;'>

            <div style='margin-bottom:12px;'>

                <img 
                    src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                    alt='Cakeouflage Logo'
                    style='height:100px; display:block;'
                >

            </div>

         <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
    Order Ready
</p>

        </div>

        <div style='padding:40px;'>

           <h2 style='margin-top:0;color:#1d1115;font-size:30px;'>
    🎂 Order Ready
</h2>

<p style='color:#5f4c55;font-size:16px;line-height:1.8;'>
    Customer order is now ready for pickup/delivery.
</p>

            <div style='margin-top:30px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;'>

                <h3 style='margin-top:0;color:#80001F;font-size:22px;'>
                    📦 Order Details
                </h3>

                <p><strong>Order ID:</strong> {$orderNumber}</p>

                <p><strong>Customer Name:</strong> {$customerName}</p>

                <p><strong>Phone:</strong> {$customerPhone}</p>

                <p><strong>Items:</strong> {$itemNames}</p>

                <p style='font-size:24px;color:#80001F;font-weight:bold;'>
                    Amount: ₹{$amount}
                </p>

            </div>

        </div>

        <div style='background:#140b0f;padding:30px;color:#fff;'>

            <h3 style='margin-top:0;font-family:Georgia,serif;'>
                Team Cakeouflage
            </h3>

            <p style='color:#d7c6cc;font-size:14px;'>
                Premium Designer Cakes crafted with elegance and creativity.
            </p>

            <p style='color:#d7c6cc;font-size:14px;'>
                🌐 www.cakeouflage.com
            </p>

        </div>

    </div>

</div>



    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Admin Order Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}
public static function sendAdminOrderRejectedEmail(
    string $orderNumber,
    string $customerName,
    string $customerPhone,
    string $itemNames,
    float $amount
) {

$mail = new PHPMailer(true);

try {

$mail->isSMTP();

  $mail->Host = 'smtp.dcoresystems.com';
$mail->SMTPAuth = true;

$mail->Username = 'noreply@dcoresystems.com';
$mail->Password = 'Zebra@789';

$mail->SMTPSecure = 'ssl';
$mail->Port = 465;

$mail->setFrom(
    'noreply@dcoresystems.com',
    'Cakeouflage'
);

    // ✅ ADMIN EMAIL
   $mail->addAddress('cakeouflage@gmail.com');

    $mail->isHTML(true);

    $mail->Subject = "❌ Order Rejected - $orderNumber";

    $mail->Body = "

    <div style='background:#f5eef2;padding:40px;font-family:Arial,sans-serif;'>

    <div style='max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);'>

      <div style='background:#991b1b;padding:28px;color:#fff;'>

            <div style='margin-bottom:12px;'>

                <img 
                    src='https://i.ibb.co/hRytXC3F/whitelogo.png'
                    alt='Cakeouflage Logo'
                    style='height:100px; display:block;'
                >

            </div>

         <p style='margin-top:10px;font-size:14px;opacity:0.9;'>
    Order Rejected
</p>

        </div>

        <div style='padding:40px;'>

          <h2 style='margin-top:0;color:#1d1115;font-size:30px;'>
    ❌ Order Rejected
</h2>

<p style='color:#5f4c55;font-size:16px;line-height:1.8;'>
    Customer order has been rejected due to payment issue.
</p>

            <div style='margin-top:30px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;'>

                <h3 style='margin-top:0;color:#80001F;font-size:22px;'>
                    📦 Order Details
                </h3>

                <p><strong>Order ID:</strong> {$orderNumber}</p>

                <p><strong>Customer Name:</strong> {$customerName}</p>

                <p><strong>Phone:</strong> {$customerPhone}</p>

                <p><strong>Items:</strong> {$itemNames}</p>

                <p style='font-size:24px;color:#80001F;font-weight:bold;'>
                    Amount: ₹{$amount}
                </p>

            </div>

        </div>

        <div style='background:#140b0f;padding:30px;color:#fff;'>

            <h3 style='margin-top:0;font-family:Georgia,serif;'>
                Team Cakeouflage
            </h3>

            <p style='color:#d7c6cc;font-size:14px;'>
                Premium Designer Cakes crafted with elegance and creativity.
            </p>

            <p style='color:#d7c6cc;font-size:14px;'>
                🌐 www.cakeouflage.com
            </p>

        </div>

    </div>

</div>



    ";

    $mail->send();

    return true;

} catch (Exception $e) {

    error_log("Admin Order Mail Error: " . $mail->ErrorInfo);

    throw $e;
}
}

    /**
     * Render an email body from a DB communication_template.
     *
     * @param \PDO   $pdo      PDO connection
     * @param string $eventKey event_key (e.g. 'payment_confirmed_customer')
     * @param array  $context  Key->value pairs for {{placeholder}} substitution
     * @return string|null Rendered HTML, or null if no active template found
     */
    public static function renderFromTemplate(\PDO $pdo, string $eventKey, array $context): ?string
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT body_template FROM communication_templates
                  WHERE channel = "email" AND event_key = :key AND is_active = 1
                  LIMIT 1'
            );
            $stmt->execute([':key' => $eventKey]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $body = $row['body_template'];
            foreach ($context as $k => $v) {
                $body = str_replace('{{' . $k . '}}', (string) $v, $body);
            }
            return $body;
        } catch (\Throwable $e) {
            error_log('MailService::renderFromTemplate error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{host:string,port:int,username:string,password:string,encryption:string,from_email:string,from_name:string} */
    public static function getLegacySmtpConfig(): array
    {
        $host = trim((string)(Env::get('SMTP_HOST_LIVE', Env::get('SMTP_HOST', 'smtp.dcoresystems.com')) ?? 'smtp.dcoresystems.com'));
        $port = (int)(Env::get('SMTP_PORT_LIVE', Env::get('SMTP_PORT', '465')) ?? '465');
        $username = trim((string)(Env::get('SMTP_USERNAME_LIVE', Env::get('SMTP_USER_LIVE', Env::get('SMTP_USER', 'noreply@dcoresystems.com'))) ?? 'noreply@dcoresystems.com'));
        $password = (string)(Env::get('SMTP_PASSWORD_LIVE', Env::get('SMTP_PASS_LIVE', Env::get('SMTP_PASS', 'Zebra@789'))) ?? 'Zebra@789');
        $secureRaw = strtolower(trim((string)(Env::get('SMTP_SECURE_LIVE', Env::get('SMTP_SECURE', 'ssl')) ?? 'ssl')));
        $secure = $secureRaw === 'smtps' ? 'ssl' : ($secureRaw === 'starttls' ? 'tls' : $secureRaw);
        $fromEmail = trim((string)(Env::get('SMTP_FROM_EMAIL_LIVE', Env::get('SMTP_FROM_EMAIL', $username)) ?? $username));
        $fromName = trim((string)(Env::get('SMTP_FROM_NAME_LIVE', Env::get('SMTP_FROM_NAME', 'Cakeouflage')) ?? 'Cakeouflage'));

        return [
            'host' => $host,
            'port' => $port > 0 ? $port : 465,
            'username' => $username,
            'password' => $password,
            'encryption' => in_array($secure, ['ssl', 'tls', 'none'], true) ? $secure : 'ssl',
            'from_email' => $fromEmail !== '' ? $fromEmail : $username,
            'from_name' => $fromName !== '' ? $fromName : 'Cakeouflage',
        ];
    }

    /** @param array<int,string> $recipients
     *  @param array<int,array<string,string>> $attachments
     */
    public static function sendRawEmail(array $recipients, string $subject, string $bodyHtml, array $attachments = []): void
    {
        $config = self::getLegacySmtpConfig();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->setFrom($config['from_email'], $config['from_name']);

        foreach ($recipients as $recipient) {
            $email = trim((string)$recipient);
            if ($email !== '') {
                $mail->addAddress($email);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = trim(strip_tags($bodyHtml));

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $filename = trim((string)($attachment['filename'] ?? 'attachment.bin'));
            $mimeType = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
            $contentBase64 = trim((string)($attachment['content_base64'] ?? ''));
            if ($filename === '' || $contentBase64 === '') {
                continue;
            }
            $decoded = base64_decode($contentBase64, true);
            if ($decoded === false) {
                continue;
            }
            $mail->addStringAttachment($decoded, $filename, PHPMailer::ENCODING_BASE64, $mimeType);
        }

        $mail->send();
    }
}
