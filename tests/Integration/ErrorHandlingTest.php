<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Http\HttpException;
use BXCode\Http\Request;
use BXCode\Middleware\CsrfMiddleware;
use BXCode\Security\Csrf;
use BXCode\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ErrorHandlingTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = Application::boot(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
    }

    public function test_validation_exception_becomes_422_json(): void
    {
        $this->app->router()->post('/api/users', function (Request $request) {
            return (new Validator())->validate($request->all(), ['email' => 'required|email']);
        });

        $response = $this->app->handle(new Request('POST', '/api/users', body: []));

        self::assertSame(422, $response->status());
        $payload = json_decode($response->body(), true);
        self::assertArrayHasKey('email', $payload['errors']);
    }

    public function test_http_exception_renders_its_status(): void
    {
        $this->app->router()->get('/secret', function (): void {
            throw HttpException::forbidden('No access.');
        });

        $html = $this->app->handle(new Request('GET', '/secret'));
        self::assertSame(403, $html->status());
        self::assertStringContainsString('No access.', $html->body());

        $json = $this->app->handle(new Request('GET', '/secret', headers: ['accept' => 'application/json']));
        self::assertSame(403, $json->status());
        self::assertSame('No access.', json_decode($json->body(), true)['message']);
    }

    public function test_generic_exception_is_production_safe(): void
    {
        $this->app->router()->get('/boom', function (): void {
            throw new \RuntimeException('internal secret');
        });

        $response = $this->app->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('internal secret', $response->body());
    }

    public function test_csrf_middleware_blocks_without_token_and_allows_with(): void
    {
        $token = (new Csrf($this->app->session()))->token();

        $this->app->router()->post('/submit', fn () => 'ok', null, [CsrfMiddleware::class]);

        $blocked = $this->app->handle(new Request('POST', '/submit', body: []));
        self::assertSame(419, $blocked->status());

        $ok = $this->app->handle(new Request('POST', '/submit', body: ['_token' => $token]));
        self::assertSame(200, $ok->status());
        self::assertSame('ok', $ok->body());

        $viaHeader = $this->app->handle(new Request('POST', '/submit', headers: ['x-csrf-token' => $token]));
        self::assertSame(200, $viaHeader->status());
    }
}
