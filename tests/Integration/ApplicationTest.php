<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        unset($_ENV['APP_DEBUG']);
        putenv('APP_DEBUG');
    }

    public function test_boot_loads_config_with_defaults(): void
    {
        $app = Application::boot($this->root);

        self::assertSame('BXCode', $app->config('app.name'));
        self::assertSame('fallback', $app->config('app.missing', 'fallback'));
    }

    public function test_env_casts_known_literals(): void
    {
        Application::boot($this->root);

        self::assertTrue(Application::env('DOES_NOT_EXIST', true));
        self::assertSame('production', Application::env('APP_ENV', 'production'));
    }

    public function test_health_route_returns_ok_json(): void
    {
        $app = Application::boot($this->root);
        require $this->root . '/routes/web.php';

        $response = $app->handle(new Request('GET', '/api/health'));

        self::assertSame(200, $response->status());
        self::assertSame('application/json; charset=UTF-8', $response->header('content-type'));

        $payload = json_decode($response->body(), true);
        self::assertTrue($payload['success']);
        self::assertSame('BXCode', $payload['framework']);
    }

    public function test_welcome_and_parameterised_routes(): void
    {
        $app = Application::boot($this->root);
        require $this->root . '/routes/web.php';

        self::assertSame('Welcome to BXCode', $app->handle(new Request('GET', '/'))->body());
        self::assertStringContainsString('Hello, Ada!', $app->handle(new Request('GET', '/hello/Ada'))->body());
    }

    public function test_exception_is_production_safe_when_debug_off(): void
    {
        $app = Application::boot($this->root);
        $app->router()->get('/boom', function (): void {
            throw new \RuntimeException('secret detail');
        });

        $response = $app->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('secret detail', $response->body());
    }

    public function test_exception_shows_detail_when_debug_on(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        putenv('APP_DEBUG=true');

        $app = Application::boot($this->root);
        $app->router()->get('/boom', function (): void {
            throw new \RuntimeException('secret detail');
        });

        $response = $app->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->status());
        self::assertStringContainsString('secret detail', $response->body());
    }
}
