<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Http\Request;
use BXCode\Http\Response;
use BXCode\Routing\Router;
use BXCode\Tests\Fixtures\AppendHeaderMiddleware;
use BXCode\Tests\Fixtures\ShortCircuitMiddleware;
use Closure;
use PHPUnit\Framework\TestCase;

final class MiddlewareTest extends TestCase
{
    private function get(Router $router, string $path): Response
    {
        return $router->dispatch(new Request('GET', $path));
    }

    public function test_route_middleware_runs(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'ok', null, [AppendHeaderMiddleware::class]);

        $response = $this->get($router, '/');

        self::assertSame('ok', $response->body());
        self::assertSame('A', $response->header('x-trail'));
    }

    public function test_global_middleware_runs_before_route_middleware(): void
    {
        $tracker = [];
        $router  = new Router();

        $outer = function (Request $request, Closure $next) use (&$tracker): Response {
            $tracker[] = 'global-before';
            $response  = $next($request);
            $tracker[] = 'global-after';

            return $response;
        };
        $inner = function (Request $request, Closure $next) use (&$tracker): Response {
            $tracker[] = 'route-before';
            $response  = $next($request);
            $tracker[] = 'route-after';

            return $response;
        };

        $router->middleware([$outer]);
        $router->get('/', function () use (&$tracker) {
            $tracker[] = 'handler';

            return 'ok';
        }, null, [$inner]);

        $this->get($router, '/');

        self::assertSame(
            ['global-before', 'route-before', 'handler', 'route-after', 'global-after'],
            $tracker,
        );
    }

    public function test_middleware_can_short_circuit(): void
    {
        $handlerRan = false;
        $router     = new Router();
        $router->get('/', function () use (&$handlerRan) {
            $handlerRan = true;

            return 'ok';
        }, null, [ShortCircuitMiddleware::class]);

        $response = $this->get($router, '/');

        self::assertSame(403, $response->status());
        self::assertSame('blocked', $response->body());
        self::assertFalse($handlerRan);
    }

    public function test_group_applies_prefix_and_middleware(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/api', 'middleware' => [AppendHeaderMiddleware::class]], function (Router $r): void {
            $r->get('/health', fn () => 'ok', 'api.health');
        });

        $missing  = $this->get($router, '/health');
        $response = $this->get($router, '/api/health');

        self::assertSame(404, $missing->status());
        self::assertSame('ok', $response->body());
        self::assertSame('A', $response->header('x-trail'));
    }

    public function test_named_route_url_generation(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn (string $id) => $id, 'users.show');

        self::assertSame('/users/42', $router->url('users.show', ['id' => 42]));
    }

    public function test_url_throws_when_parameter_missing(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => '', 'users.show');

        $this->expectException(\InvalidArgumentException::class);
        $router->url('users.show');
    }
}
