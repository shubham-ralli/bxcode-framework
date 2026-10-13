<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Application;
use BXCode\Http\Response;
use BXCode\Routing\Router;

/**
 * Static entry point to the booted application's Router, for the clean
 * `Route::get('/', ...)` developer experience.
 *
 * @method static Router get(string $path, callable|array $handler, ?string $name = null)
 * @method static Router post(string $path, callable|array $handler, ?string $name = null)
 * @method static Router put(string $path, callable|array $handler, ?string $name = null)
 * @method static Router patch(string $path, callable|array $handler, ?string $name = null)
 * @method static Router delete(string $path, callable|array $handler, ?string $name = null)
 * @method static Router head(string $path, callable|array $handler, ?string $name = null)
 * @method static Router add(string $method, string $path, callable|array $handler, ?string $name = null)
 * @method static Router group(array $attributes, \Closure $routes)
 * @method static Router middleware(array $middleware)
 * @method static string url(string $name, array $params = [])
 * @method static Router setNotFoundHandler(callable $handler)
 */
final class Route
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return Application::getInstance()->router()->{$name}(...$arguments);
    }
}
