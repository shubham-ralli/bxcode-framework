<?php

declare(strict_types=1);

namespace BXCode\Middleware;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/**
 * Adds conservative security headers to every response. Safe defaults that suit
 * most apps; tune per project as needed.
 */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-XSS-Protection', '0'); // modern browsers: rely on CSP, disable legacy auditor
    }
}
