<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WosKaraoke\Env;

class EnvTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/test_env_' . uniqid();
        file_put_contents($this->tmpFile, implode("\n", [
            '# Comment line',
            'APP_NAME=WosKaraoke',
            'APP_DEBUG=true',
            'APP_PORT=8080',
            'EMPTY_VAR=',
            'QUOTED_VAR="hello world"',
        ]));

        // Reset Env state via reflection
        $ref = new ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setAccessible(true);
        $loaded->setValue(null, false);

        $vars = $ref->getProperty('variables');
        $vars->setAccessible(true);
        $vars->setValue(null, []);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testLoadParsesVariables(): void
    {
        Env::load($this->tmpFile);

        $this->assertSame('WosKaraoke', Env::get('APP_NAME'));
        $this->assertSame('true', Env::get('APP_DEBUG'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        Env::load($this->tmpFile);

        $this->assertSame('fallback', Env::get('NONEXISTENT', 'fallback'));
        $this->assertNull(Env::get('NONEXISTENT'));
    }

    public function testBoolCastsCorrectly(): void
    {
        Env::load($this->tmpFile);

        $this->assertTrue(Env::bool('APP_DEBUG'));
        $this->assertFalse(Env::bool('NONEXISTENT', false));
    }

    public function testIntCastsCorrectly(): void
    {
        Env::load($this->tmpFile);

        $this->assertSame(8080, Env::int('APP_PORT'));
        $this->assertSame(0, Env::int('NONEXISTENT'));
    }

    public function testQuotedValuesAreStripped(): void
    {
        Env::load($this->tmpFile);

        $this->assertSame('hello world', Env::get('QUOTED_VAR'));
    }

    public function testHas(): void
    {
        Env::load($this->tmpFile);

        $this->assertTrue(Env::has('APP_NAME'));
        $this->assertFalse(Env::has('TOTALLY_MISSING'));
    }

    public function testCommentsAreIgnored(): void
    {
        Env::load($this->tmpFile);

        $this->assertNull(Env::get('# Comment line'));
    }
}
