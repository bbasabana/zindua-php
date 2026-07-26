<?php

declare(strict_types=1);

namespace Zindua;

use Zindua\Exceptions\ZinduaException;
use Zindua\Http\ApiClient;

class Client
{
    private readonly ApiClient $http;
    private readonly string $apiKey;
    private readonly bool $autoPaceWhatsapp;
    private float $lastWhatsappSendAt = 0.0;

    /**
     * @param array{
     *   apiKey: string,
     *   baseUrl?: string|null,
     *   timeoutMs?: int|null,
     *   siteUrl?: string|null,
     *   autoPaceWhatsapp?: bool|null
     * } $options
     */
    public function __construct(array $options)
    {
        $this->apiKey = Validation::validateApiKey($options['apiKey']);
        $baseUrl = Validation::resolveBaseUrl($options['baseUrl'] ?? null);
        $timeoutMs = Validation::validateTimeoutMs($options['timeoutMs'] ?? null);
        $siteUrl = Validation::validateSiteUrl($options['siteUrl'] ?? null);
        $this->autoPaceWhatsapp = ($options['autoPaceWhatsapp'] ?? true) !== false;

        $this->http = new ApiClient($this->apiKey, $baseUrl, $timeoutMs, $siteUrl);
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->apiKey, 'znd_test_');
    }

    /**
     * @param array{
     *   to?: string,
     *   userId?: string,
     *   userExternalId?: string,
     *   type?: string,
     *   purpose?: string,
     *   context?: array<string, mixed>,
     *   channel?: string,
     *   fallbackChannel?: string,
     *   fallback?: array{channel?: string, to?: string},
     *   ttlSeconds?: int
     * } $options
     * @return array<string, mixed>
     */
    public function createChallenge(array $options): array
    {
        return $this->http->request('POST', 'challenges', $options);
    }

    /** @return array<string, mixed> */
    public function getChallenge(string $challengeId): array
    {
        return $this->http->request('GET', 'challenges/' . rawurlencode($challengeId));
    }

    /** @return array<string, mixed> */
    public function respondChallenge(string $challengeId, string $value): array
    {
        return $this->http->request('POST', 'challenges/' . rawurlencode($challengeId) . '/respond', [
            'value' => $value,
        ]);
    }

    /**
     * @param array{
     *   userExternalId: string,
     *   platform?: string,
     *   pushToken?: string,
     *   keys?: array<string, string>
     * } $options
     * @return array<string, mixed>
     */
    public function registerDevice(array $options): array
    {
        return $this->http->request('POST', 'devices', $options);
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
     *   replyTo?: string|null,
     *   attachments?: list<array{url: string, filename?: string}>|null
     * } $options
     * @return array<string, mixed>
     */
    public function send(array $options): array
    {
        $channel = Validation::validateChannel($options['channel'] ?? null);
        if ($channel === 'whatsapp' && $this->autoPaceWhatsapp) {
            $this->paceWhatsappSend();
        }

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

        if ($channel === 'email') {
            $cc = Validation::validateOptionalEmailField('cc', $options['cc'] ?? null);
            $bcc = Validation::validateOptionalEmailField('bcc', $options['bcc'] ?? null);
            $replyTo = Validation::validateOptionalEmailField('replyTo', $options['replyTo'] ?? null);
            $attachments = Validation::validateAttachments($options['attachments'] ?? null);
            if ($cc !== null) {
                $payload['cc'] = $cc;
            }
            if ($bcc !== null) {
                $payload['bcc'] = $bcc;
            }
            if ($replyTo !== null) {
                $payload['replyTo'] = $replyTo;
            }
            if ($attachments !== null) {
                $payload['attachments'] = $attachments;
            }
        }

        $data = $this->http->request('POST', 'send', $payload);

        if (($data['success'] ?? false) !== true || !is_string($data['logId'] ?? null)) {
            throw new ZinduaException('API response missing success or logId.', 200, 'INVALID_RESPONSE');
        }

        if ($channel === 'whatsapp') {
            $this->lastWhatsappSendAt = microtime(true);
        }

        return $data;
    }

    private function paceWhatsappSend(): void
    {
        if ($this->lastWhatsappSendAt <= 0) {
            return;
        }

        $elapsedMs = (microtime(true) - $this->lastWhatsappSendAt) * 1000;
        if ($elapsedMs >= WhatsappAntiBan::MIN_INTERVAL_MS) {
            return;
        }

        $waitMs = (int) ceil(WhatsappAntiBan::MIN_INTERVAL_MS - $elapsedMs);
        WhatsappAntiBan::warnPace($waitMs);
        usleep($waitMs * 1000);
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
