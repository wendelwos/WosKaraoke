<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WosKaraoke\Cache;

class CacheTest extends TestCase
{
    private Cache $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/test_cache_' . uniqid();
        mkdir($this->cacheDir, 0755, true);
        $this->cache = new Cache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('key1', ['foo' => 'bar'], 60);

        $result = $this->cache->get('key1');
        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key2', 'value', 60);
        $this->assertTrue($this->cache->has('key2'));

        $this->cache->delete('key2');
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testRememberCachesCallbackResult(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed_value';
        };

        $first = $this->cache->remember('remember_key', $callback, 60);
        $second = $this->cache->remember('remember_key', $callback, 60);

        $this->assertSame('computed_value', $first);
        $this->assertSame('computed_value', $second);
        $this->assertSame(1, $callCount); // Callback called only once
    }

    public function testStatsReturnsArray(): void
    {
        $this->cache->set('s1', 'v1', 60);
        $this->cache->set('s2', 'v2', 60);

        $stats = $this->cache->stats();
        $this->assertSame(2, $stats['total_files']);
        $this->assertSame(2, $stats['valid_items']);
    }
}
