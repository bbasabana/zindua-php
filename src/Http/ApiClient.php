<?php

declare(strict_types=1);

namespace Zindua\Http;

use Zindua\Exceptions\ZinduaException;

final class ApiClient
{
    private const SDK_VERSION = '1.0.0';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeoutMs,
        private readonly ?string $siteUrl = null
    ) {
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: Zindua-PHP/' . self::SDK_VERSION,
            'Accept: application/json',
        ];

        if ($this->siteUrl !== null) {
            $headers[] = 'X-Zindua-Site-Url: ' . $this->siteUrl;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ZinduaException('Failed to initialize cURL.', 0, 'API_ERROR');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $json;
        }

        curl_setopt_array($ch, $opts);
        $text = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($text === false) {
            throw new ZinduaException(
                $curlError !== '' ? $curlError : 'Network request failed.',
                0,
                'API_ERROR'
            );
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $text !== '' ? json_decode($text, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            throw new ZinduaException('API returned non-JSON response.', $status, 'INVALID_RESPONSE');
        }

        if ($status < 200 || $status >= 300) {
            throw $this->parseApiError($status, $data);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function parseApiError(int $status, array $body): ZinduaException
    {
        $message = is_string($body['error'] ?? null)
            ? $body['error']
            : (is_string($body['message'] ?? null) ? $body['message'] : "Request failed with HTTP {$status}");

        $details = [];
        if (is_string($body['code'] ?? null)) {
            $details['code'] = $body['code'];
        }
        if (is_string($body['hint'] ?? null)) {
            $details['hint'] = $body['hint'];
        }
        if (isset($body['retryAfterSec']) && is_numeric($body['retryAfterSec'])) {
            $details['retryAfterSec'] = (int) $body['retryAfterSec'];
        }
        if (is_string($body['boundSite'] ?? null)) {
            $details['boundSite'] = $body['boundSite'];
        }

        $fullMessage = is_string($body['hint'] ?? null) && $body['hint'] !== ''
            ? $message . ' ' . $body['hint']
            : $message;

        $code = is_string($body['code'] ?? null) ? $body['code'] : 'API_ERROR';

        return new ZinduaException(
            $fullMessage,
            $status,
            $code,
            $details !== [] ? $details : null
        );
    }
}
