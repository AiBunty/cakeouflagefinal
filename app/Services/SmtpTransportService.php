<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PDO;

final class SmtpTransportService
{
    /** @var array<string,mixed> */
    private $settings;

    /** @var resource|null */
    private $socket = null;

    /** @param array<string,mixed> $settings */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    private static function isPlaceholderValue(?string $value): bool
    {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return false;
        }

        return str_starts_with($normalized, 'your_')
            || str_starts_with($normalized, 'replace_with_')
            || str_starts_with($normalized, 'dummy')
            || str_starts_with($normalized, 'example');
    }

    /** @param array<string,mixed> $settings
     *  @return array<string,mixed>
     */
    private static function normalizeSettings(array $settings): array
    {
        $host = trim((string)($settings['host'] ?? ''));
        $username = trim((string)($settings['username'] ?? ''));
        $password = (string)($settings['password_encrypted'] ?? '');
        $fromName = trim((string)($settings['from_name'] ?? 'Cakeouflage')) ?: 'Cakeouflage';
        $fromEmail = trim((string)($settings['from_email'] ?? ''));

        if (self::isPlaceholderValue($host)) {
            $host = '';
        }
        if (self::isPlaceholderValue($username)) {
            $username = '';
        }
        if (self::isPlaceholderValue($password)) {
            $password = '';
        }
        if (self::isPlaceholderValue($fromEmail)) {
            $fromEmail = '';
        }
        if (self::isPlaceholderValue($fromName)) {
            $fromName = 'Cakeouflage';
        }

        $secure = strtolower(trim((string)($settings['encryption'] ?? 'tls')));
        if ($secure === 'smtps') {
            $secure = 'ssl';
        } elseif ($secure === 'starttls') {
            $secure = 'tls';
        }

        return [
            'host' => $host,
            'port' => (int)($settings['port'] ?? 0),
            'username' => $username,
            'password_encrypted' => $password,
            'encryption' => in_array($secure, ['none', 'ssl', 'tls'], true) ? $secure : 'tls',
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'is_active' => (int)($settings['is_active'] ?? 0),
        ];
    }

    public static function fromActiveDatabase(PDO $pdo): ?self
    {
        $stmt = $pdo->query('SELECT host, port, username, password_encrypted, encryption, from_name, from_email, is_active FROM smtp_settings ORDER BY id DESC LIMIT 1');
        $settings = $stmt instanceof \PDOStatement ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $settings = self::normalizeSettings($settings);

        $hasDbConfig = (int)($settings['is_active'] ?? 0) === 1
            && trim((string)($settings['host'] ?? '')) !== ''
            && (int)($settings['port'] ?? 0) > 0
            && trim((string)($settings['username'] ?? '')) !== ''
            && trim((string)($settings['password_encrypted'] ?? '')) !== ''
            && trim((string)($settings['from_email'] ?? '')) !== '';

        if (!$hasDbConfig) {
            return null;
        }

        return new self($settings);
    }

    public static function fromDatabase(PDO $pdo): self
    {
        $dbTransport = self::fromActiveDatabase($pdo);
        if ($dbTransport instanceof self) {
            return $dbTransport;
        }

        return new self(self::fromEnvironment());
    }

    /** @return array<string,mixed> */
    private static function fromEnvironment(): array
    {
        $settings = [
            'host' => trim((string)(Env::get('SMTP_HOST', '') ?? '')),
            'port' => (int)(Env::get('SMTP_PORT', '0') ?? '0'),
            'username' => trim((string)(Env::get('SMTP_USER', '') ?? '')),
            'password_encrypted' => (string)(Env::get('SMTP_PASS', '') ?? ''),
            'encryption' => (string)(Env::get('SMTP_SECURE', 'tls') ?? 'tls'),
            'from_name' => (string)(Env::get('SMTP_FROM_NAME', 'Cakeouflage') ?? 'Cakeouflage'),
            'from_email' => (string)(Env::get('SMTP_FROM_EMAIL', Env::get('SMTP_USER', '')) ?? ''),
            'is_active' => 1,
        ];

        $settings = self::normalizeSettings($settings);
        $settings['is_active'] = ($settings['host'] !== '' && (int)$settings['port'] > 0 && $settings['username'] !== '' && (string)$settings['password_encrypted'] !== '' && $settings['from_email'] !== '') ? 1 : 0;

        return $settings;
    }

    /** @return array{host:string,port:int,encryption:string,auth_enabled:bool,username_set:bool,from_email_set:bool} */
    public function getPublicMeta(): array
    {
        return [
            'host' => trim((string)($this->settings['host'] ?? '')) !== '' ? (string)$this->settings['host'] : '(empty)',
            'port' => (int)($this->settings['port'] ?? 0),
            'encryption' => (string)($this->settings['encryption'] ?? 'tls'),
            'auth_enabled' => true,
            'username_set' => trim((string)($this->settings['username'] ?? '')) !== '',
            'from_email_set' => trim((string)($this->settings['from_email'] ?? '')) !== '',
        ];
    }

    public function isConfigured(): bool
    {
        return (int)($this->settings['is_active'] ?? 0) === 1
            && trim((string)($this->settings['host'] ?? '')) !== ''
            && (int)($this->settings['port'] ?? 0) > 0
            && trim((string)($this->settings['from_email'] ?? '')) !== '';
    }

    public function testConnection(): array
    {
        $this->connect();
        $this->disconnect();

        return [
            'success' => true,
            'message' => 'SMTP connection successful',
        ];
    }

    /** @param array<int,string> $to
     *  @param array<int,array<string,string>> $attachments
     */
    public function send(array $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $attachments = []): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('SMTP settings are incomplete or inactive');
        }

        $recipients = array_values(array_filter(array_map(static function (string $email): string {
            return trim($email);
        }, $to)));
        if (count($recipients) === 0) {
            throw new \RuntimeException('At least one SMTP recipient is required');
        }

        $isDarkFooter = false;
        if ($bodyHtml !== null && trim($bodyHtml) !== '') {
            $isDarkFooter = stripos($bodyHtml, 'background:#140b0f') !== false
                || stripos($bodyHtml, 'background:#172554') !== false
                || stripos($bodyHtml, 'background:#450a0a') !== false
                || stripos($bodyHtml, 'background:#052e16') !== false;
            $bodyHtml = $this->appendDeveloperFooterToHtml($bodyHtml, $isDarkFooter);
        }
        $bodyText = $this->appendDeveloperFooterToText($bodyText);

        $this->connect();

        $fromEmail = trim((string)($this->settings['from_email'] ?? ''));
        $fromName = trim((string)($this->settings['from_name'] ?? 'Cakeouflage')) ?: 'Cakeouflage';

        $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
        foreach ($recipients as $recipient) {
            $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
        }

        $this->command('DATA', [354]);

        $altBoundary = 'cakeouflage-alt-' . bin2hex(random_bytes(8));
        $mixedBoundary = 'cakeouflage-mixed-' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . $this->addressHeader($fromName, $fromEmail),
            'To: ' . implode(', ', array_map(function (string $email): string {
                return '<' . $email . '>';
            }, $recipients)),
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
        ];

        $hasHtml = $bodyHtml !== null && trim($bodyHtml) !== '';
        $hasAttachments = count($attachments) > 0;

        if ($hasAttachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
            $message = implode("\r\n", $headers) . "\r\n\r\n";

            if ($hasHtml) {
                $message .= '--' . $mixedBoundary . "\r\n"
                    . 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n"
                    . '--' . $altBoundary . "\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                    . $this->escapeBody($bodyText) . "\r\n"
                    . '--' . $altBoundary . "\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                    . $this->escapeBody((string)$bodyHtml) . "\r\n"
                    . '--' . $altBoundary . "--\r\n";
            } else {
                $message .= '--' . $mixedBoundary . "\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                    . $this->escapeBody($bodyText) . "\r\n";
            }

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
                if (base64_decode($contentBase64, true) === false) {
                    continue;
                }

                $message .= '--' . $mixedBoundary . "\r\n"
                    . 'Content-Type: ' . $mimeType . '; name="' . addslashes($filename) . '"' . "\r\n"
                    . 'Content-Disposition: attachment; filename="' . addslashes($filename) . '"' . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split($contentBase64, 76, "\r\n")
                    . "\r\n";
            }

            $message .= '--' . $mixedBoundary . "--\r\n";
        } elseif ($hasHtml) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . '--' . $altBoundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $this->escapeBody($bodyText) . "\r\n"
                . '--' . $altBoundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                . $this->escapeBody((string)$bodyHtml) . "\r\n"
                . '--' . $altBoundary . "--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeBody($bodyText) . "\r\n";
        }

        $this->write($message . "\r\n.\r\n");
        $this->expect([250]);
        $this->disconnect();
    }

    private function appendDeveloperFooterToText(string $bodyText): string
    {
        $clean = preg_replace('/Developed\s+by\s+dcoresystems\.com/i', '', $bodyText) ?? $bodyText;

        return rtrim($clean) . "\n\nDeveloped by DCoreSystems.com\n";
    }

    private function appendDeveloperFooterToHtml(string $bodyHtml, bool $isDarkFooter): string
    {
        $cleanHtml = preg_replace('/<div[^>]*id=["\']dcore-dev-footer-lock["\'][^>]*>[\s\S]*?<\/div>/i', '', $bodyHtml) ?? $bodyHtml;
        $cleanHtml = preg_replace('/<div[^>]*>[\s\S]*?Developed\s+by[\s\S]*?dcoresystems\.com[\s\S]*?<\/div>/i', '', $cleanHtml) ?? $cleanHtml;

        $logoUrl = $isDarkFooter
            ? 'https://cakeouflage.com/client/assets/images/dcore-logo-white.svg'
            : 'https://cakeouflage.com/client/assets/images/dcore-logo-black.svg';
        $textColor = $isDarkFooter ? '#d7c6cc' : '#475569';

        $credit = "\n<div id='dcore-dev-footer-lock' style='margin-top:12px;font-size:11px;line-height:1.5;color:" . $textColor . ";display:flex;align-items:center;gap:7px;'>"
            . "<span>Developed by</span>"
            . "<a href='https://dcoresystems.com' target='_blank' style='display:inline-flex;align-items:center;gap:6px;color:" . $textColor . ";text-decoration:none;'>"
            . "<img src='" . $logoUrl . "' alt='DCore Systems' style='height:12px;width:auto;display:block;'>"
            . "<span style='font-weight:700;letter-spacing:0.02em;'>dcoresystems.com</span>"
            . "</a>"
            . "</div>\n";

        if (stripos($cleanHtml, '</body>') !== false) {
            return str_ireplace('</body>', $credit . '</body>', $cleanHtml);
        }

        if (stripos($cleanHtml, '</html>') !== false) {
            return str_ireplace('</html>', $credit . '</html>', $cleanHtml);
        }

        return $cleanHtml . $credit;
    }

    private function connect(): void
    {
        if (is_resource($this->socket)) {
            return;
        }

        $host = trim((string)($this->settings['host'] ?? ''));
        $port = (int)($this->settings['port'] ?? 0);
        $encryption = (string)($this->settings['encryption'] ?? 'tls');
        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;

        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 5, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new \RuntimeException('SMTP socket connection failed: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, 5);
        $this->socket = $socket;
        $this->expect([220]);

        $hostname = gethostname() ?: 'localhost';
        $this->command('EHLO ' . $hostname, [250]);

        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            $cryptoEnabled = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new \RuntimeException('Unable to enable STARTTLS for SMTP connection');
            }
            $this->command('EHLO ' . $hostname, [250]);
        }

        $username = trim((string)($this->settings['username'] ?? ''));
        $password = (string)($this->settings['password_encrypted'] ?? '');
        if ($username !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($username), [334]);
            $this->command(base64_encode($password), [235]);
        }
    }

    private function disconnect(): void
    {
        if (!is_resource($this->socket)) {
            return;
        }

        try {
            $this->command('QUIT', [221]);
        } catch (\Throwable $e) {
        }

        fclose($this->socket);
        $this->socket = null;
    }

    /** @param array<int,int> $codes */
    private function command(string $command, array $codes): void
    {
        $this->write($command . "\r\n");
        $this->expect($codes);
    }

    private function write(string $payload): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('SMTP socket is not connected');
        }

        $written = fwrite($this->socket, $payload);
        if ($written === false) {
            throw new \RuntimeException('Failed writing to SMTP socket');
        }
    }

    /** @param array<int,int> $expectedCodes */
    private function expect(array $expectedCodes): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('SMTP socket is not connected');
        }

        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Unexpected SMTP response [' . $code . ']: ' . trim($response));
        }
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function addressHeader(string $name, string $email): string
    {
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function escapeBody(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $normalized = preg_replace('/^\./m', '..', $normalized ?? '') ?: $body;
        return str_replace("\n", "\r\n", $normalized);
    }
}