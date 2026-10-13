<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Cache\FileStore;
use BXCode\Facades\Cache;
use BXCode\Facades\Event;
use BXCode\Facades\Storage;
use BXCode\Storage\LocalFilesystem;
use PHPUnit\Framework\TestCase;

final class ExtensionsFacadeTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/bxcode_ext_' . bin2hex(random_bytes(4));
        $app = Application::boot(dirname(__DIR__, 2));
        $app->setCache(new FileStore($this->tmp . '/cache'));
        $app->setStorage(new LocalFilesystem($this->tmp . '/app'));
    }

    protected function tearDown(): void
    {
        Cache::store()->flush();
        Application::setInstance(null);
    }

    public function test_cache_facade_and_helper(): void
    {
        Cache::put('k', 'v', 60);
        self::assertSame('v', Cache::get('k'));
        self::assertSame('v', cache()->get('k'));
    }

    public function test_event_facade_shares_one_dispatcher(): void
    {
        $seen = [];
        Event::listen('ping', function (string $who) use (&$seen) { $seen[] = $who; });
        Event::dispatch('ping', 'world');

        self::assertSame(['world'], $seen);
    }

    public function test_storage_facade(): void
    {
        Storage::put('data/file.txt', 'contents');

        self::assertTrue(Storage::exists('data/file.txt'));
        self::assertSame('contents', Storage::get('data/file.txt'));
    }
}
