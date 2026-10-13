<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Cache\FileStore;
use BXCode\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->dir     = sys_get_temp_dir() . '/bxcode_rl_' . bin2hex(random_bytes(4));
        $this->limiter = new RateLimiter(new FileStore($this->dir));
    }

    protected function tearDown(): void
    {
        (new FileStore($this->dir))->flush();
        @rmdir($this->dir);
    }

    public function test_counts_hits_and_detects_limit(): void
    {
        self::assertSame(0, $this->limiter->attempts('ip|/path'));
        self::assertFalse($this->limiter->tooManyAttempts('ip|/path', 3));

        self::assertSame(1, $this->limiter->hit('ip|/path', 60));
        self::assertSame(2, $this->limiter->hit('ip|/path', 60));
        self::assertSame(3, $this->limiter->hit('ip|/path', 60));

        self::assertTrue($this->limiter->tooManyAttempts('ip|/path', 3));
        self::assertSame(0, $this->limiter->remaining('ip|/path', 3));
    }

    public function test_clear_resets(): void
    {
        $this->limiter->hit('k', 60);
        $this->limiter->clear('k');

        self::assertSame(0, $this->limiter->attempts('k'));
    }

    public function test_keys_are_independent(): void
    {
        $this->limiter->hit('a', 60);
        self::assertSame(1, $this->limiter->attempts('a'));
        self::assertSame(0, $this->limiter->attempts('b'));
    }
}
