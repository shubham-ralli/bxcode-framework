<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Cache\FileStore;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bxcode_cache_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new FileStore($this->dir))->flush();
        @rmdir($this->dir);
    }

    public function test_put_and_get(): void
    {
        $store = new FileStore($this->dir);
        self::assertTrue($store->put('name', 'Ada'));
        self::assertSame('Ada', $store->get('name'));
        self::assertSame('default', $store->get('missing', 'default'));
    }

    public function test_get_handles_cached_null(): void
    {
        $store = new FileStore($this->dir);
        $store->put('nothing', null);

        self::assertTrue($store->has('nothing'));
        self::assertNull($store->get('nothing', 'should-not-be-returned'));
    }

    public function test_has_forget_flush(): void
    {
        $store = new FileStore($this->dir);
        $store->put('a', 1);
        $store->put('b', 2);

        self::assertTrue($store->has('a'));
        self::assertTrue($store->forget('a'));
        self::assertFalse($store->has('a'));

        self::assertTrue($store->flush());
        self::assertFalse($store->has('b'));
    }

    public function test_remember_computes_once_then_caches(): void
    {
        $store = new FileStore($this->dir);
        $calls = 0;

        $first  = $store->remember('k', 60, function () use (&$calls) { $calls++; return 'value'; });
        $second = $store->remember('k', 60, function () use (&$calls) { $calls++; return 'value'; });

        self::assertSame('value', $first);
        self::assertSame('value', $second);
        self::assertSame(1, $calls, 'callback should run only on a miss');
    }

    public function test_ttl_expires(): void
    {
        $store = new FileStore($this->dir);
        $store->put('temp', 'x', 1);
        self::assertSame('x', $store->get('temp'));

        sleep(2);
        self::assertNull($store->get('temp'));
        self::assertFalse($store->has('temp'));
    }

    public function test_prefix_namespaces_keys(): void
    {
        $a = new FileStore($this->dir, 'a');
        $b = new FileStore($this->dir, 'b');

        $a->put('key', 'from-a');
        $b->put('key', 'from-b');

        self::assertSame('from-a', $a->get('key'));
        self::assertSame('from-b', $b->get('key'));
    }
}
