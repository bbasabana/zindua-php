<?php

declare(strict_types=1);

namespace Zindua;

/** Zindua WhatsApp anti-ban robot — client-side pacing helpers. */
final class WhatsappAntiBan
{
    public const MIN_INTERVAL_MS = 3000;

    public const GUIDE_URL = 'https://zindua.run/blog/whatsapp-otp-anti-ban-pacing';

    public static function rateLimitMessage(string $baseMessage, ?int $retryAfterSec = null): string
    {
        $wait = ($retryAfterSec !== null && $retryAfterSec > 0)
            ? " Wait {$retryAfterSec}s, then retry."
            : ' Wait at least 3s between WhatsApp sends.';

        return $baseMessage . $wait
            . " Zindua's anti-ban robot paces WhatsApp to protect your linked number from bans."
            . ' Space sends in your app (queue / debounce) or keep autoPaceWhatsapp on.'
            . ' Guide: ' . self::GUIDE_URL;
    }

    public static function warnPace(int $waitMs): void
    {
        $sec = (int) ceil($waitMs / 1000);
        error_log(
            "[Zindua anti-ban] Waiting {$sec}s before WhatsApp send — "
            . 'rapid OTP looks like spam and can ban your linked number. '
            . 'Prefer a queue or ≥3s gap in your code. '
            . self::GUIDE_URL
        );
    }
}
