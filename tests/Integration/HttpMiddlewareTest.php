<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Cache\FileStore;
use BXCode\Http\Request;
use BXCode\Logging\Logger;
use BXCode\Middleware\Cors;
use BXCode\Middleware\RateLimit;
use BXCode\Middleware\RequestLogging;
use BXCode\Middleware\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class HttpMiddlewareTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/bxcode_mw_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
    }

    private function boot(): Application
    {
        $app = Application::boot(dirname(__DIR__, 2));
        $app->setCache(new FileStore($this->tmp . '/cache'));

        return $app;
    }

    public function test_security_headers_added(): void
    {
        $app = $this->boot();
        $app->router()->get('/', fn () => 'ok', null, [SecurityHeaders::class]);

        $response = $app->handle(new Request('GET', '/'));

        self::assertSame('nosniff', $response->header('x-content-type-options'));
        self::assertSame('SAMEORIGIN', $response->header('x-frame-options'));
    }

    public function test_cors_preflight_and_origin_echo(): void
    {
        $app = $this->boot();
        $app->router()->add('OPTIONS', '/api/data', fn () => '', null, [Cors::class]);
        $app->router()->get('/api/data', fn () => ['ok' => true], null, [Cors::class]);

        $preflight = $app->handle(new Request('OPTIONS', '/api/data', headers: ['origin' => 'https://x.io']));
        self::assertSame(204, $preflight->status());
        self::assertSame('https://x.io', $preflight->header('access-control-allow-origin'));

        $get = $app->handle(new Request('GET', '/api/data', headers: ['origin' => 'https://x.io']));
        self::assertSame('https://x.io', $get->header('access-control-allow-origin'));
    }

    public function test_rate_limit_blocks_after_max(): void
    {
        $app = $this->boot();
        $app->setConfig('ratelimit.max_attempts', 2);
        $app->router()->get('/limited', fn () => 'ok', null, [RateLimit::class]);

        self::assertSame(200, $app->handle(new Request('GET', '/limited'))->status());
        self::assertSame(200, $app->handle(new Request('GET', '/limited'))->status());

        $blocked = $app->handle(new Request('GET', '/limited'));
        self::assertSame(429, $blocked->status());
        self::assertSame('60', $blocked->header('retry-after'));
    }

    public function test_request_logging_writes_entry(): void
    {
        $app     = $this->boot();
        $logFile = $this->tmp . '/req.log';
        $app->setLogger(new Logger($logFile, requestId: 'test-req'));

        $app->router()->get('/logged', fn () => 'ok', null, [RequestLogging::class]);
        $app->handle(new Request('GET', '/logged'));

        $contents = (string) file_get_contents($logFile);
        self::assertStringContainsString('GET /logged -> 200', $contents);
        self::assertStringContainsString('test-req', $contents);
    }
}
