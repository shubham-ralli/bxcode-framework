<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Storage\LocalFilesystem;
use PHPUnit\Framework\TestCase;

final class StorageTest extends TestCase
{
    private string $root;

    private LocalFilesystem $disk;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bxcode_store_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0775, true);
        $this->disk = new LocalFilesystem($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $f) {
            is_dir($f) ? @rmdir($f) : @unlink($f);
        }
        @rmdir($this->root);
    }

    public function test_put_get_exists_size_delete(): void
    {
        self::assertFalse($this->disk->exists('note.txt'));
        self::assertTrue($this->disk->put('note.txt', 'hello'));

        self::assertTrue($this->disk->exists('note.txt'));
        self::assertSame('hello', $this->disk->get('note.txt'));
        self::assertSame(5, $this->disk->size('note.txt'));

        self::assertTrue($this->disk->delete('note.txt'));
        self::assertFalse($this->disk->exists('note.txt'));
        self::assertNull($this->disk->get('note.txt'));
    }

    public function test_put_creates_nested_directories(): void
    {
        self::assertTrue($this->disk->put('a/b/c.txt', 'deep'));
        self::assertSame('deep', $this->disk->get('a/b/c.txt'));
    }

    public function test_append(): void
    {
        $this->disk->put('log.txt', 'one');
        $this->disk->append('log.txt', 'two');

        self::assertSame('onetwo', $this->disk->get('log.txt'));
    }

    public function test_files_lists_only_files_relative_to_root(): void
    {
        $this->disk->put('x.txt', '1');
        $this->disk->put('y.txt', '2');
        $this->disk->makeDirectory('sub');

        self::assertSame(['x.txt', 'y.txt'], $this->disk->files());
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->disk->path('../outside.txt');
    }

    public function test_null_byte_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->disk->path("evil\0.txt");
    }

    public function test_absolute_path_is_sandboxed_into_root(): void
    {
        // An absolute-looking path is treated as relative to the root, not /etc.
        $resolved = $this->disk->path('/etc/passwd');
        self::assertStringStartsWith($this->root, $resolved);
        self::assertStringEndsWith('/etc/passwd', $resolved);
    }
}
