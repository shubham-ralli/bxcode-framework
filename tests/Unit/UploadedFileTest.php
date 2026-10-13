<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Http\UploadedFile;
use BXCode\Storage\LocalFilesystem;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    private string $tmp;

    private string $root;

    protected function setUp(): void
    {
        $this->tmp  = sys_get_temp_dir() . '/bxcode_upl_' . bin2hex(random_bytes(4)) . '.dat';
        $this->root = sys_get_temp_dir() . '/bxcode_uplroot_' . bin2hex(random_bytes(4));
        file_put_contents($this->tmp, 'file-body');
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        foreach (glob($this->root . '/*') ?: [] as $f) {
            is_dir($f) ? array_map('unlink', glob($f . '/*') ?: []) && @rmdir($f) : @unlink($f);
        }
        @rmdir($this->root);
    }

    private function file(string $clientName, int $size = 9): UploadedFile
    {
        return new UploadedFile($clientName, $this->tmp, $size, UPLOAD_ERR_OK, testMode: true);
    }

    public function test_valid_upload_passes_validation(): void
    {
        $errors = $this->file('photo.JPG')->validate(maxBytes: 1000, allowedExtensions: ['jpg', 'png']);

        self::assertSame([], $errors);
    }

    public function test_oversize_and_bad_extension_fail(): void
    {
        $tooBig = $this->file('photo.jpg', 5000)->validate(maxBytes: 1000, allowedExtensions: ['jpg']);
        self::assertNotEmpty($tooBig);

        $badType = $this->file('script.php')->validate(maxBytes: 1000, allowedExtensions: ['jpg', 'png']);
        self::assertNotEmpty($badType);
    }

    public function test_store_generates_safe_random_name_with_extension(): void
    {
        $disk     = new LocalFilesystem($this->root);
        $relative = $this->file('../../evil.png')->store($disk, 'uploads');

        // The client name/path is discarded; a random name under uploads/ is used.
        self::assertStringStartsWith('uploads/', $relative);
        self::assertStringEndsWith('.png', $relative);
        self::assertStringNotContainsString('evil', $relative);
        self::assertStringNotContainsString('..', $relative);
        self::assertTrue($disk->exists($relative));
        self::assertSame('file-body', $disk->get($relative));
    }

    public function test_invalid_upload_cannot_be_stored(): void
    {
        $disk    = new LocalFilesystem($this->root);
        $invalid = new UploadedFile('x.png', $this->tmp, 9, UPLOAD_ERR_NO_FILE, testMode: true);

        $this->expectException(\RuntimeException::class);
        $invalid->store($disk, 'uploads');
    }
}
