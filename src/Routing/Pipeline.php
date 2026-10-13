<?php

declare(strict_types=1);

namespace BXCode\Routing;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/**
 * Runs a request through an ordered list of middleware, then into a destination
 * that produces the Response. Middleware run outside-in; the first listed is the
 * outermost wrapper.
 *
 * Each middleware entry may be a Middleware instance, a class-string naming one
 * (constructed with no arguments), or a Closure(Request, Closure): Response.
 */
final class Pipeline
{
    /**
     * @param list<Middleware|class-string|Closure> $middleware
     * @param Closure(Request): Response            $destination
     */
    public static function run(Request $request, array $middleware, Closure $destination): Response
    {
        $next = array_reduce(
            array_reverse($middleware),
            static function (Closure $next, Middleware|string|Closure $entry): Closure {
                return static function (Request $request) use ($entry, $next): Response {
                    $handler = self::toHandler($entry);

                    return $handler($request, $next);
                };
            },
            $destination,
        );

        return $next($request);
    }

    /**
     * @param Middleware|class-string|Closure $entry
     * @return Closure(Request, Closure): Response
     */
    private static function toHandler(Middleware|string|Closure $entry): Closure
    {
        if ($entry instanceof Closure) {
            return $entry;
        }

        $instance = is_string($entry) ? new $entry() : $entry;

        if (!$instance instanceof Middleware) {
            throw new \InvalidArgumentException(sprintf(
                'Middleware %s must implement %s.',
                get_debug_type($instance),
                Middleware::class,
            ));
        }

        return static fn (Request $request, Closure $next): Response => $instance->handle($request, $next);
    }
}
