<?php

declare(strict_types=1);

namespace Zindua;

/** Factory helpers for the Zindua PHP SDK. */
final class ZinduaFactory
{
    /**
     * @param array{
     *   apiKey?: string|null,
     *   baseUrl?: string|null,
     *   timeoutMs?: int|null,
     *   siteUrl?: string|null
     * } $options
     */
    public static function client(array $options = []): Client
    {
        $apiKey = $options['apiKey'] ?? getenv('ZINDUA_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            throw new Exceptions\ZinduaException('apiKey is required.', 0, 'INVALID_API_KEY');
        }

        return new Client([
            'apiKey' => (string) $apiKey,
            'baseUrl' => $options['baseUrl'] ?? null,
            'timeoutMs' => $options['timeoutMs'] ?? null,
            'siteUrl' => $options['siteUrl'] ?? (getenv('ZINDUA_SITE_URL') ?: null),
        ]);
    }
}
