<?php

declare(strict_types=1);

namespace Zindua;

use Zindua\Exceptions\ZinduaException;
use Zindua\Http\ApiClient;

class Client
{
    private readonly ApiClient $http;
    private readonly string $apiKey;

    /**
     * @param array{
     *   apiKey: string,
     *   baseUrl?: string|null,
     *   timeoutMs?: int|null,
     *   siteUrl?: string|null
     * } $options
     */
    public function __construct(array $options)
    {
        $this->apiKey = Validation::validateApiKey($options['apiKey']);
        $baseUrl = Validation::resolveBaseUrl($options['baseUrl'] ?? null);
        $timeoutMs = Validation::validateTimeoutMs($options['timeoutMs'] ?? null);
        $siteUrl = Validation::validateSiteUrl($options['siteUrl'] ?? null);

        $this->http = new ApiClient($this->apiKey, $baseUrl, $timeoutMs, $siteUrl);
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->apiKey, 'znd_test_');
    }

    /**
     * @param array{
     *   to: string,
     *   template: string,
     *   channel?: string|null,
     *   lang?: string|null,
     *   variables?: array<string, string>|null,
     *   cc?: string|null,
     *   bcc?: string|null,
     *   replyTo?: string|null
     * } $options
     * @return array<string, mixed>
     */
    public function send(array $options): array
    {
        $channel = Validation::validateChannel($options['channel'] ?? null);
        $to = Validation::validateRecipient($options['to'], $channel);
        $template = Validation::validateTemplateSlug($options['template']);
        $lang = Validation::validateLang($options['lang'] ?? null);
        $variables = Validation::sanitizeVariables($options['variables'] ?? null);

        $payload = ['to' => $to, 'template' => $template];
        if ($channel === 'whatsapp') {
            $payload['channel'] = 'whatsapp';
        } elseif (($options['channel'] ?? null) === 'email') {
            $payload['channel'] = 'email';
        }
        if ($lang !== null) {
            $payload['lang'] = $lang;
        }
        if ($variables !== null) {
            $payload['variables'] = $variables;
        }

        $data = $this->http->request('POST', 'send', $payload);

        if (($data['success'] ?? false) !== true || !is_string($data['logId'] ?? null)) {
            throw new ZinduaException('API response missing success or logId.', 200, 'INVALID_RESPONSE');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function getProject(): array
    {
        return $this->http->request('GET', 'project');
    }

    /**
     * @return array{templates: list<array<string, mixed>>, limits?: array<string, mixed>}
     */
    public function getTemplates(): array
    {
        $data = $this->http->request('GET', 'templates');
        $templates = is_array($data['templates'] ?? null) ? $data['templates'] : [];

        return [
            'templates' => $templates,
            'limits' => is_array($data['limits'] ?? null) ? $data['limits'] : null,
        ];
    }

    /**
     * Bind API key to a site URL (WordPress connect flow).
     * @return array<string, mixed>
     */
    public function connect(?string $siteUrl = null): array
    {
        $url = Validation::validateSiteUrl($siteUrl);
        if ($url === null) {
            throw new ZinduaException('siteUrl is required for connect().', 0, 'INVALID_OPTIONS');
        }

        $data = $this->http->request('POST', 'connect', ['siteUrl' => $url]);

        if (($data['ok'] ?? false) !== true) {
            throw new ZinduaException('API response missing connect payload.', 200, 'INVALID_RESPONSE');
        }

        return $data;
    }
}
