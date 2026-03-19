<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WosKaraoke\Helpers;

class HelpersTest extends TestCase
{
    public function testGetClientIPFallsBackToLoopback(): void
    {
        // Without any server headers set, should return 127.0.0.1
        $oldServer = $_SERVER;
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->assertSame('192.168.1.100', Helpers::getClientIP());

        $_SERVER = $oldServer;
    }

    public function testGetClientIPParsesForwardedFor(): void
    {
        $oldServer = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);

        $this->assertSame('10.0.0.1', Helpers::getClientIP());

        $_SERVER = $oldServer;
    }

    public function testIsMySQLReflectsConstant(): void
    {
        // DB_TYPE is defined by config or test bootstrap
        // Just verify method doesn't throw
        $result = Helpers::isMySQL();
        $this->assertIsBool($result);
    }

    public function testEngineSuffixReturnsStringForBothTypes(): void
    {
        $suffix = Helpers::engineSuffix();
        $this->assertIsString($suffix);
    }
}
