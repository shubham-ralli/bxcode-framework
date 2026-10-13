<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Http\Request;
use BXCode\Http\Response;
use BXCode\Routing\Router;
use BXCode\Tests\Fixtures\GreetController;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        return new Request($method, $path);
    }

    public function test_matches_static_route_and_normalises_string_to_html(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'home');

        $response = $router->dispatch($this->request('GET', '/'));

        self::assertSame(200, $response->status());
        self::assertSame('home', $response->body());
        self::assertSame('text/html; charset=UTF-8', $response->header('content-type'));
    }

    public function test_injects_named_route_parameter_into_closure(): void
    {
        $router = new Router();
        $router->get('/hello/{name}', fn (string $name) => "Hello, {$name}!");

        $response = $router->dispatch($this->request('GET', '/hello/ada'));

        self::assertSame('Hello, ada!', $response->body());
    }

    public function test_array_return_is_normalised_to_json(): void
    {
        $router = new Router();
        $router->get('/api/health', fn () => ['success' => true]);

        $response = $router->dispatch($this->request('GET', '/api/health'));

        self::assertSame('application/json; charset=UTF-8', $response->header('content-type'));
        self::assertSame('{"success":true}', $response->body());
    }

    public function test_controller_dispatch_resolves_request_and_param(): void
    {
        $router = new Router();
        $router->get('/greet/{name}', [GreetController::class, 'show']);

        $response = $router->dispatch($this->request('GET', '/greet/bob'));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('{"name":"bob","method":"GET"}', $response->body());
    }

    public function test_unknown_path_returns_404(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'home');

        $response = $router->dispatch($this->request('GET', '/nope'));

        self::assertSame(404, $response->status());
    }

    public function test_known_path_wrong_method_returns_405(): void
    {
        $router = new Router();
        $router->get('/only-get', fn () => 'ok');

        $response = $router->dispatch($this->request('POST', '/only-get'));

        self::assertSame(405, $response->status());
    }

    public function test_head_falls_back_to_get_route(): void
    {
        $router = new Router();
        $router->get('/page', fn () => 'body');

        $response = $router->dispatch($this->request('HEAD', '/page'));

        self::assertSame(200, $response->status());
    }

    public function test_custom_not_found_handler_is_used(): void
    {
        $router = new Router();
        $router->setNotFoundHandler(fn () => Response::json(['error' => 'missing'], 404));

        $response = $router->dispatch($this->request('GET', '/anything'));

        self::assertSame(404, $response->status());
        self::assertSame('{"error":"missing"}', $response->body());
    }
}
