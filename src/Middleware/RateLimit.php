<?php

declare(strict_types=1);

namespace BXCode\Middleware;

use BXCode\Application;
use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use BXCode\Security\RateLimiter;
use Closure;

/**
 * Throttles requests per client IP + path using the cache-backed RateLimiter.
 * Limits come from config/ratelimit.php (max_attempts, decay_seconds). On
 * exceed it returns 429 with a Retry-After header; otherwise it adds
 * X-RateLimit-* headers.
 */
final class RateLimit implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $app     = Application::getInstance();
        $max     = (int) $app->config('ratelimit.max_attempts', 60);
        $decay   = (int) $app->config('ratelimit.decay_seconds', 60);
        $limiter = new RateLimiter($app->cache());

        $key = $request->ip() . '|' . $request->path();

        if ($limiter->tooManyAttempts($key, $max)) {
            return Response::text('429 Too Many Requests', 429)
                ->withHeader('Retry-After', (string) $decay)
                ->withHeader('X-RateLimit-Limit', (string) $max)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $limiter->hit($key, $decay);

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $max)
            ->withHeader('X-RateLimit-Remaining', (string) $limiter->remaining($key, $max));
    }
}
