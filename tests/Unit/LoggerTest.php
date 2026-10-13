<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Logging\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/bxcode_log_' . bin2hex(random_bytes(4)) . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_writes_line_with_level_and_request_id(): void
    {
        $logger = new Logger($this->file, requestId: 'req123');
        $logger->info('hello world');

        $contents = (string) file_get_contents($this->file);
        self::assertStringContainsString('[INFO]', $contents);
        self::assertStringContainsString('[req123]', $contents);
        self::assertStringContainsString('hello world', $contents);
    }

    public function test_redacts_secret_context_keys(): void
    {
        $logger = new Logger($this->file);
        $logger->error('login', ['user' => 7, 'password' => 'hunter2', 'nested' => ['token' => 'abc']]);

        $contents = (string) file_get_contents($this->file);
        self::assertStringNotContainsString('hunter2', $contents);
        self::assertStringNotContainsString('abc', $contents);
        self::assertStringContainsString('[redacted]', $contents);
        self::assertStringContainsString('"user":7', $contents);
    }

    public function test_level_threshold_filters_below_minimum(): void
    {
        $logger = new Logger($this->file, minLevel: 'warning');
        $logger->info('ignored');
        $logger->warning('kept');

        $contents = (string) file_get_contents($this->file);
        self::assertStringNotContainsString('ignored', $contents);
        self::assertStringContainsString('kept', $contents);
    }
}
