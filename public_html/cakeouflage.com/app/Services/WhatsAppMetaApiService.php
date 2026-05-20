<?php
declare(strict_types=1);

namespace App\Services;

final class WhatsAppMetaApiService
{
    /** @var array<string,mixed> */
    private $settings;

    /** @param array<string,mixed> $settings */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return (int)($this->settings['is_active'] ?? 0) === 1
            && trim((string)($this->settings['api_base_url'] ?? '')) !== ''
            && trim((string)($this->settings['business_account_id'] ?? '')) !== ''
            && trim((string)($this->settings['phone_number_id'] ?? '')) !== ''
            && trim((string)($this->accessToken() ?? '')) !== '';
    }

    /** @return array<string,mixed> */
    public function testConnection(): array
    {
        $phoneNumberId = trim((string)($this->settings['phone_number_id'] ?? ''));
        $response = $this->request('GET', '/' . rawurlencode($phoneNumberId) . '?fields=display_phone_number,verified_name,quality_rating');

        return [
            'success' => true,
            'account' => $response,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchTemplates(): array
    {
        $wabaId = trim((string)($this->settings['business_account_id'] ?? ''));
        $response = $this->request('GET', '/' . rawurlencode($wabaId) . '/message_templates?limit=200');
        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function submitTemplate(array $payload): array
    {
        $wabaId = trim((string)($this->settings['business_account_id'] ?? ''));
        return $this->request('POST', '/' . rawurlencode($wabaId) . '/message_templates', $payload);
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function sendTemplateMessage(array $payload): array
    {
        $phoneNumberId = trim((string)($this->settings['phone_number_id'] ?? ''));
        return $this->request('POST', '/' . rawurlencode($phoneNumberId) . '/messages', $payload);
    }

    /** @param array<string,mixed>|null $payload
     *  @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL extension is required for Meta API communication');
        }
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Meta WhatsApp settings are incomplete or inactive');
        }

        $base = rtrim((string)($this->settings['api_base_url'] ?? ''), '/');
        $url = $base . $path;
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize Meta API request');
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken(),
            'Content-Type: application/json',
        ];

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
        ]);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            throw new \RuntimeException('Meta API request failed: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw];
        }

        if ($httpCode >= 400) {
            $message = (string)($decoded['error']['message'] ?? $decoded['message'] ?? 'Meta API request failed');
            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    private function accessToken(): ?string
    {
        $token = trim((string)($this->settings['access_token_encrypted'] ?? ''));
        if ($token !== '') {
            return $token;
        }

        $legacy = trim((string)($this->settings['api_key_encrypted'] ?? ''));
        return $legacy !== '' ? $legacy : null;
    }
}