<?php

declare(strict_types=1);

namespace Zindua;

use Zindua\Exceptions\ZinduaException;

final class Validation
{
    public const DEFAULT_API_BASE = 'https://zindua.run/api/v1';
    public const DEFAULT_TIMEOUT_MS = 30_000;
    public const MAX_TIMEOUT_MS = 120_000;

    private const E164_RE = '/^\+[1-9]\d{6,14}$/';
    private const EMAIL_RE = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    private const TEMPLATE_SLUG_RE = '/^[a-z0-9][a-z0-9_-]{0,62}$/i';
    private const LANG_RE = '/^[a-z]{2}(-[a-z]{2})?$/i';
    private const API_KEY_RE = '/^znd_(live|test)_[a-z0-9]{24}$/';

    public static function validateApiKey(string $apiKey): string
    {
        $key = trim($apiKey);
        if ($key === '') {
            throw new ZinduaException('apiKey is required.', 0, 'INVALID_API_KEY');
        }
        if (!preg_match(self::API_KEY_RE, $key)) {
            throw new ZinduaException(
                'apiKey must be znd_live_… or znd_test_… (project key from Dashboard → Projects).',
                0,
                'INVALID_API_KEY'
            );
        }
        return $key;
    }

    public static function resolveBaseUrl(?string $explicit = null): string
    {
        $fromEnv = getenv('ZINDUA_API_BASE_URL');
        $raw = trim($explicit ?? ($fromEnv !== false ? (string) $fromEnv : ''));
        if ($raw === '') {
            return self::DEFAULT_API_BASE;
        }
        return self::validateBaseUrl($raw);
    }

    public static function validateBaseUrl(string $raw): string
    {
        $trimmed = rtrim(trim($raw), '/');
        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new ZinduaException('baseUrl is not a valid URL.', 0, 'INVALID_BASE_URL');
        }
        if (!empty($parsed['user']) || !empty($parsed['pass'])) {
            throw new ZinduaException('baseUrl must not contain credentials.', 0, 'INVALID_BASE_URL');
        }

        $host = strtolower($parsed['host']);
        $isLocal = $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '[::1]'
            || str_ends_with($host, '.localhost');

        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'https' && !($isLocal && $scheme === 'http')) {
            throw new ZinduaException(
                'baseUrl must use HTTPS (HTTP is only allowed for localhost).',
                0,
                'INVALID_BASE_URL'
            );
        }

        return $trimmed;
    }

    public static function validateChannel(?string $channel): string
    {
        if ($channel === null || $channel === '' || $channel === 'email') {
            return 'email';
        }
        if ($channel === 'whatsapp') {
            return 'whatsapp';
        }
        throw new ZinduaException('channel must be "email" or "whatsapp".', 0, 'INVALID_CHANNEL');
    }

    public static function validateTemplateSlug(string $template): string
    {
        $slug = trim($template);
        if ($slug === '' || strlen($slug) > 64) {
            throw new ZinduaException('template slug is required (max 64 characters).', 0, 'INVALID_TEMPLATE');
        }
        if (!preg_match(self::TEMPLATE_SLUG_RE, $slug)) {
            throw new ZinduaException(
                'template slug may only contain letters, numbers, hyphens, and underscores.',
                0,
                'INVALID_TEMPLATE'
            );
        }
        return $slug;
    }

    public static function validateRecipient(string $to, string $channel): string
    {
        $recipient = trim($to);
        if ($recipient === '' || strlen($recipient) > 320) {
            throw new ZinduaException('to is required and must be under 320 characters.', 0, 'MISSING_FIELDS');
        }

        if ($channel === 'email') {
            if (preg_match(self::E164_RE, $recipient)) {
                throw new ZinduaException(
                    'A phone number cannot be sent on channel "email". Use an email address or set channel to "whatsapp".',
                    0,
                    'INVALID_EMAIL'
                );
            }
            if (!preg_match(self::EMAIL_RE, $recipient)) {
                throw new ZinduaException('Invalid email address for channel email.', 0, 'INVALID_EMAIL');
            }
            return $recipient;
        }

        if (preg_match(self::EMAIL_RE, $recipient)) {
            throw new ZinduaException(
                'An email address cannot be sent on channel "whatsapp". Use E.164 with + or set channel to "email".',
                0,
                'INVALID_PHONE'
            );
        }
        if (!preg_match(self::E164_RE, $recipient)) {
            throw new ZinduaException(
                'WhatsApp to must be E.164 with leading + (e.g. +243812345678).',
                0,
                'INVALID_PHONE'
            );
        }
        return $recipient;
    }

    public static function validateLang(?string $lang): ?string
    {
        if ($lang === null || trim($lang) === '') {
            return null;
        }
        $code = trim($lang);
        if (strlen($code) > 10 || !preg_match(self::LANG_RE, $code)) {
            throw new ZinduaException('lang must be a short ISO 639-1 code (e.g. fr, en, sw).', 0, 'INVALID_LANG');
        }
        return $code;
    }

    /**
     * @param array<string, string>|null $variables
     * @return array<string, string>|null
     */
    public static function sanitizeVariables(?array $variables): ?array
    {
        if ($variables === null) {
            return null;
        }

        $blocked = ['__proto__', 'constructor', 'prototype'];
        $out = [];
        $count = 0;

        foreach ($variables as $key => $value) {
            if (in_array($key, $blocked, true)) {
                continue;
            }
            if (!is_string($value)) {
                throw new ZinduaException('variables values must be strings.', 0, 'INVALID_VARIABLES');
            }
            if ($count >= 50) {
                throw new ZinduaException('At most 50 variables allowed.', 0, 'INVALID_VARIABLES');
            }
            $safeKey = substr(trim($key), 0, 64);
            if ($safeKey === '') {
                continue;
            }
            $out[$safeKey] = substr($value, 0, 4096);
            $count++;
        }

        return $out === [] ? null : $out;
    }

    public static function validateSiteUrl(?string $siteUrl): ?string
    {
        if ($siteUrl === null || trim($siteUrl) === '') {
            return null;
        }
        $trimmed = trim($siteUrl);
        $withScheme = str_contains($trimmed, '://') ? $trimmed : 'https://' . $trimmed;
        $parsed = parse_url($withScheme);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new ZinduaException('siteUrl must be a valid URL (e.g. https://example.com).', 0, 'INVALID_OPTIONS');
        }
        return strtolower($parsed['scheme']) . '://' . $parsed['host'];
    }

    public static function validateTimeoutMs(?int $timeoutMs): int
    {
        if ($timeoutMs === null) {
            return self::DEFAULT_TIMEOUT_MS;
        }
        if ($timeoutMs < 1000 || $timeoutMs > self::MAX_TIMEOUT_MS) {
            throw new ZinduaException(
                'timeoutMs must be between 1000 and ' . self::MAX_TIMEOUT_MS . '.',
                0,
                'INVALID_OPTIONS'
            );
        }
        return $timeoutMs;
    }

    public static function validateOptionalEmailField(string $field, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $trimmed = trim($value);
        if (!preg_match(self::EMAIL_RE, $trimmed)) {
            throw new ZinduaException("Invalid {$field} email address.", 0, 'INVALID_OPTIONS');
        }
        return $trimmed;
    }

    /**
     * @param list<array{url: string, filename?: string}>|null $attachments
     * @return list<array{url: string, filename?: string}>|null
     */
    public static function validateAttachments(?array $attachments): ?array
    {
        if ($attachments === null || $attachments === []) {
            return null;
        }
        if (count($attachments) > 5) {
            throw new ZinduaException('At most 5 attachments allowed.', 0, 'INVALID_OPTIONS');
        }

        $out = [];
        foreach ($attachments as $item) {
            if (!is_array($item) || !is_string($item['url'] ?? null) || trim($item['url']) === '') {
                throw new ZinduaException('Each attachment must include a url.', 0, 'INVALID_OPTIONS');
            }
            $url = trim($item['url']);
            $parsed = parse_url($url);
            if ($parsed === false || ($parsed['scheme'] ?? '') !== 'https') {
                throw new ZinduaException('Attachment urls must use HTTPS.', 0, 'INVALID_OPTIONS');
            }
            $entry = ['url' => $url];
            if (isset($item['filename']) && is_string($item['filename']) && trim($item['filename']) !== '') {
                $entry['filename'] = trim($item['filename']);
            }
            $out[] = $entry;
        }

        return $out;
    }
}
