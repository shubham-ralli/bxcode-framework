<?php

declare(strict_types=1);

namespace BXCode\Http;

use Closure;

/**
 * A middleware wraps the request handling. Do work, then call $next($request)
 * to pass control inward, or return a Response early to short-circuit.
 *
 *   final class ExampleMiddleware implements Middleware
 *   {
 *       public function handle(Request $request, Closure $next): Response
 *       {
 *           // ... before ...
 *           $response = $next($request);
 *           // ... after ...
 *           return $response;
 *       }
 *   }
 *
 * @phpstan-type Next Closure(Request): Response
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response;
}
