<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

// Ensure these core classes are available when MailService is loaded from admin pages
// that don't use bootstrap.php / Composer autoloading.
if (!class_exists('App\\Core\\Env', false)) {
    require_once __DIR__ . '/../Core/Env.php';
}
if (!class_exists('App\\Core\\Database', false)) {
    require_once __DIR__ . '/../Core/Database.php';
}
require_once __DIR__ . '/SmtpTransportService.php';

require_once __DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private static function applyDebugOptions(PHPMailer $mail): void
    {
        $debug = (string)(Env::get('APP_DEBUG', '0') ?? '0');
        if ($debug === '1' || strtolower($debug) === 'true') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
        }
    }

    private static function isPlaceholderValue(?string $value): bool
    {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return false;
        }

        return str_starts_with($normalized, 'your_')
            || str_starts_with($normalized, 'replace_with_')
            || str_starts_with($normalized, 'changeme');
    }

    /** @param array<int,string> $keys */
    private static function firstRealEnvValue(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = trim((string)(Env::get($key, '') ?? ''));
            if ($value === '' || self::isPlaceholderValue($value)) {
                continue;
            }
            return $value;
        }

        return $default;
    }

    /** @return array{host:string,port:int,username:string,password:string,encryption:string,from_email:string,from_name:string} */
    private static function getResolvedOtpSmtpConfig(): array
    {
        $host = self::firstRealEnvValue(['SMTP_HOST_LIVE', 'SMTP_HOST'], '');
        $username = self::firstRealEnvValue(['SMTP_USERNAME_LIVE', 'SMTP_USER_LIVE', 'SMTP_USER'], '');
        $password = self::firstRealEnvValue(['SMTP_PASSWORD_LIVE', 'SMTP_PASS_LIVE', 'SMTP_PASS'], '');
        $fromEmail = self::firstRealEnvValue(['SMTP_FROM_EMAIL_LIVE', 'SMTP_FROM_EMAIL'], $username);
        $fromName = self::firstRealEnvValue(['SMTP_FROM_NAME_LIVE', 'SMTP_FROM_NAME'], 'Cakeouflage');

        return [
            'host' => $host,
            'port' => 587,
            'username' => $username,
            'password' => $password,
            'encryption' => 'tls',
            'from_email' => $fromEmail,
            'from_name' => $fromName !== '' ? $fromName : 'Cakeouflage',
        ];
    }

    /** @return array{host:string,port:int,encryption:string,auth_enabled:bool,username_set:bool,from_email_set:bool} */
    public static function getOtpSmtpRuntimeMeta(): array
    {
        $config = self::getResolvedOtpSmtpConfig();

        return [
            'host' => $config['host'] !== '' ? $config['host'] : '(empty)',
            'port' => (int)$config['port'],
            'encryption' => $config['encryption'],
            'auth_enabled' => true,
            'username_set' => $config['username'] !== '',
            'from_email_set' => $config['from_email'] !== '',
        ];
    }

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
                $transport = SmtpTransportService::fromActiveDatabase($pdo);
                if ($transport instanceof SmtpTransportService && $transport->isConfigured()) {
                    $transport->send([(string)$email], $subject, $textBody, $htmlBody);
                    error_log('SMTP OTP SEND SUCCESS: ' . json_encode($transport->getPublicMeta()));
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('OTP SMTP DB transport failed: ' . $e->getMessage());
        }

        $smtpConfig = self::getResolvedOtpSmtpConfig();
        if ($smtpConfig['host'] === '' || $smtpConfig['username'] === '' || $smtpConfig['password'] === '' || $smtpConfig['from_email'] === '') {
            throw new \RuntimeException('SMTP configuration incomplete for OTP delivery');
        }

        $mail = new PHPMailer(true);
        try {
            // SMTP config
            $mail->isSMTP();
            self::applyDebugOptions($mail);
            $mail->Host = $smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['username'];
            $mail->Password = $smtpConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->Timeout = 15;
            $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
            $mail->addAddress($email);
            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            error_log('SMTP OTP SEND SUCCESS: ' . json_encode(self::getOtpSmtpRuntimeMeta()));
            return true;
        } catch (Exception $e) {
            error_log('SMTP CONFIG OTP: ' . json_encode(self::getOtpSmtpRuntimeMeta()));
            error_log("Mailer Error: " . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
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
        $host = self::firstRealEnvValue(['SMTP_HOST_LIVE', 'SMTP_HOST'], '');
        $port = (int)(Env::get('SMTP_PORT_LIVE', Env::get('SMTP_PORT', '587')) ?? '587');
        $username = self::firstRealEnvValue(['SMTP_USERNAME_LIVE', 'SMTP_USER_LIVE', 'SMTP_USER'], '');
        $password = self::firstRealEnvValue(['SMTP_PASSWORD_LIVE', 'SMTP_PASS_LIVE', 'SMTP_PASS'], '');
        $secureRaw = self::firstRealEnvValue(['SMTP_SECURE_LIVE', 'SMTP_SECURE'], 'tls');
        $secureRaw = strtolower(trim((string)$secureRaw));
        $secure = $secureRaw === 'smtps' ? 'ssl' : ($secureRaw === 'starttls' ? 'tls' : $secureRaw);
        $fromEmail = self::firstRealEnvValue(['SMTP_FROM_EMAIL_LIVE', 'SMTP_FROM_EMAIL'], $username);
        $fromName = self::firstRealEnvValue(['SMTP_FROM_NAME_LIVE', 'SMTP_FROM_NAME'], 'Cakeouflage');

        return [
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'username' => $username,
            'password' => $password,
            'encryption' => in_array($secure, ['ssl', 'tls', 'none'], true) ? $secure : 'tls',
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
        self::applyDebugOptions($mail);
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
