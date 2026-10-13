<?php

declare(strict_types=1);

namespace BXCode\Routing;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Route registry and dispatcher.
 *
 * Register routes with the verb helpers, then hand a Request to dispatch().
 * Handlers may be closures, [Controller::class, 'method'] pairs, or any callable.
 * Handler arguments are resolved by reflection: a Request-typed parameter gets
 * the request, a parameter named like a {placeholder} gets that path segment.
 *
 * Middleware run outside-in: global middleware first, then the route's own.
 * group() applies a shared prefix and/or middleware to everything registered
 * inside its callback.
 *
 * @phpstan-type Handler callable|array{0:class-string,1:string}
 * @phpstan-type Mw Middleware|class-string|Closure
 */
final class Router
{
    /** @var list<array{method:string,regex:string,vars:list<string>,handler:Handler,name:?string,middleware:list<Mw>,path:string}> */
    private array $routes = [];

    /** @var array<string,int> name => index into $routes */
    private array $names = [];

    /** @var list<Mw> Middleware applied to every route. */
    private array $globalMiddleware = [];

    /** @var array{prefix:string,middleware:list<Mw>} Current group context. */
    private array $group = ['prefix' => '', 'middleware' => []];

    /** @var null|callable(Request):Response */
    private $notFound = null;

    /**
     * @param Handler  $handler
     * @param list<Mw> $middleware
     */
    public function get(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $name, $middleware);
    }

    /** @param Handler $handler @param list<Mw> $middleware */
    public function post(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $name, $middleware);
    }

    /** @param Handler $handler @param list<Mw> $middleware */
    public function put(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $name, $middleware);
    }

    /** @param Handler $handler @param list<Mw> $middleware */
    public function patch(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $handler, $name, $middleware);
    }

    /** @param Handler $handler @param list<Mw> $middleware */
    public function delete(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $name, $middleware);
    }

    /** @param Handler $handler @param list<Mw> $middleware */
    public function head(string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('HEAD', $path, $handler, $name, $middleware);
    }

    /**
     * @param Handler  $handler
     * @param list<Mw> $middleware
     */
    public function add(string $method, string $path, callable|array $handler, ?string $name = null, array $middleware = []): self
    {
        $path = $this->group['prefix'] . '/' . trim($path, '/');
        $path = '/' . trim($path, '/');

        [$regex, $vars] = $this->compile($path);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => $regex,
            'vars'       => $vars,
            'handler'    => $handler,
            'name'       => $name,
            'middleware' => [...$this->group['middleware'], ...$middleware],
            'path'       => $path,
        ];

        if ($name !== null) {
            $this->names[$name] = array_key_last($this->routes);
        }

        return $this;
    }

    /**
     * Register routes under a shared prefix and/or middleware set. Groups nest:
     * prefixes concatenate and middleware accumulate.
     *
     * @param array{prefix?:string,middleware?:list<Mw>} $attributes
     * @param Closure(Router):void                       $routes
     */
    public function group(array $attributes, Closure $routes): self
    {
        $previous = $this->group;

        $prefix = $previous['prefix'];
        if (isset($attributes['prefix']) && $attributes['prefix'] !== '') {
            $prefix .= '/' . trim($attributes['prefix'], '/');
        }

        $this->group = [
            'prefix'     => $prefix,
            'middleware' => [...$previous['middleware'], ...($attributes['middleware'] ?? [])],
        ];

        $routes($this);

        $this->group = $previous;

        return $this;
    }

    /**
     * Append middleware that run on every route.
     *
     * @param list<Mw> $middleware
     */
    public function middleware(array $middleware): self
    {
        $this->globalMiddleware = [...$this->globalMiddleware, ...$middleware];

        return $this;
    }

    /**
     * All registered routes as plain data, for tooling such as `route:list`.
     *
     * @return list<array{method:string,path:string,name:?string,middleware:int}>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $r): array => [
                'method'     => $r['method'],
                'path'       => $r['path'],
                'name'       => $r['name'],
                'middleware' => count($r['middleware']),
            ],
            $this->routes,
        );
    }

    /** Register the handler used when no route matches the path. */
    public function setNotFoundHandler(callable $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    /**
     * Build a path for a named route, substituting {placeholders}.
     *
     * @param array<string,string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new \InvalidArgumentException("No route named [{$name}].");
        }

        $path = $this->routes[$this->names[$name]]['path'];

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }

        if (preg_match('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', $path, $m) === 1) {
            throw new \InvalidArgumentException("Missing parameter [{$m[1]}] for route [{$name}].");
        }

        return $path;
    }

    /**
     * Compile "/hello/{name}" into a regex plus its ordered variable names.
     *
     * @return array{0:string,1:list<string>}
     */
    private function compile(string $path): array
    {
        $vars = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$vars): string {
                $vars[] = $m[1];

                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $path,
        );

        return ['#^' . $regex . '$#', $vars];
    }

    /**
     * Match and run a request, returning the Response.
     *
     * HEAD falls back to a matching GET route. A path that exists under a
     * different verb yields 405; nothing at all, 404. Middleware wrap the match.
     */
    public function dispatch(Request $request): Response
    {
        $path        = $request->path();
        $method      = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $m) !== 1) {
                continue;
            }

            $pathMatched = true;

            $verbMatches = $route['method'] === $method
                || ($method === 'HEAD' && $route['method'] === 'GET');

            if (!$verbMatches) {
                continue;
            }

            $params = [];
            foreach ($route['vars'] as $var) {
                $params[$var] = $m[$var];
            }

            $matched = $request->withParams($params);

            $destination = fn (Request $request): Response => $this->toResponse(
                $this->call($route['handler'], $request, $params),
            );

            return Pipeline::run(
                $matched,
                [...$this->globalMiddleware, ...$route['middleware']],
                $destination,
            );
        }

        if ($pathMatched) {
            return Response::text('405 Method Not Allowed', 405);
        }

        if ($this->notFound !== null) {
            return $this->toResponse(($this->notFound)($request));
        }

        return Response::html(
            '<!doctype html><title>404 Not Found</title><h1>404 Not Found</h1>'
            . '<p>No route matched ' . htmlspecialchars($path, ENT_QUOTES) . '.</p>',
            404,
        );
    }

    /**
     * Invoke a handler, resolving its arguments by reflection.
     *
     * @param Handler              $handler
     * @param array<string,string> $params
     */
    private function call(callable|array $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && is_string($handler[0])) {
            [$class, $method] = $handler;
            $instance   = new $class();
            $reflection = new ReflectionMethod($class, $method);
            $args       = $this->resolveArgs($reflection->getParameters(), $request, $params);

            return $instance->{$method}(...$args);
        }

        $reflection = new ReflectionFunction(Closure::fromCallable($handler));
        $args       = $this->resolveArgs($reflection->getParameters(), $request, $params);

        return $handler(...$args);
    }

    /**
     * @param array<int,\ReflectionParameter> $parameters
     * @param array<string,string>            $params
     * @return list<mixed>
     */
    private function resolveArgs(array $parameters, Request $request, array $params): array
    {
        $args = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $args[] = $request;
                continue;
            }

            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            $args[] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        return $args;
    }

    /** Normalise a handler's return value into a Response. */
    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if ($result === null) {
            return Response::make('', 204);
        }

        return Response::html((string) $result);
    }
}
