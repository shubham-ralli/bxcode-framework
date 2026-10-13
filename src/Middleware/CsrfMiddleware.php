<?php

declare(strict_types=1);

namespace BXCode\Middleware;

use BXCode\Application;
use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use BXCode\Security\Csrf;
use Closure;

/**
 * Rejects state-changing requests (POST/PUT/PATCH/DELETE) that lack a valid CSRF
 * token. The token is read from the `_token` input or the `X-CSRF-Token` header.
 * Safe methods (GET/HEAD/OPTIONS) pass through untouched.
 */
final class CsrfMiddleware implements Middleware
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        $csrf  = new Csrf(Application::getInstance()->session());
        $token = $request->input('_token') ?? $request->header('x-csrf-token');
        $token = is_string($token) ? $token : null;

        if (!$csrf->verify($token)) {
            return Response::text('419 CSRF token mismatch.', 419);
        }

        return $next($request);
    }
}
