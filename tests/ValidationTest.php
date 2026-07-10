<?php

declare(strict_types=1);

namespace Zindua\Tests;

use PHPUnit\Framework\TestCase;
use Zindua\Exceptions\ZinduaException;
use Zindua\Validation;

final class ValidationTest extends TestCase
{
    public function testValidApiKey(): void
    {
        $key = 'znd_test_abcdefghijklmnopqrstuvwx';
        self::assertSame($key, Validation::validateApiKey($key));
    }

    public function testRejectsInvalidApiKey(): void
    {
        $this->expectException(ZinduaException::class);
        Validation::validateApiKey('bad-key');
    }

    public function testValidEmailRecipient(): void
    {
        self::assertSame('user@example.com', Validation::validateRecipient('user@example.com', 'email'));
    }

    public function testRejectsPhoneOnEmailChannel(): void
    {
        $this->expectException(ZinduaException::class);
        Validation::validateRecipient('+243812345678', 'email');
    }

    public function testValidWhatsappRecipient(): void
    {
        self::assertSame('+243812345678', Validation::validateRecipient('+243812345678', 'whatsapp'));
    }

    public function testSanitizeVariables(): void
    {
        $out = Validation::sanitizeVariables(['code' => '482910', 'appName' => 'MyShop']);
        self::assertSame(['code' => '482910', 'appName' => 'MyShop'], $out);
    }
}
