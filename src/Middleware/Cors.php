<?php

declare(strict_types=1);

namespace BXCode\Middleware;

use BXCode\Application;
use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/**
 * CORS headers, configurable via config/cors.php. An OPTIONS preflight is
 * answered immediately with 204. When the allowed origin list contains "*",
 * any origin is echoed back; otherwise only listed origins are allowed.
 */
final class Cors implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $config  = Application::getInstance();
        $origins = (array) $config->config('cors.allowed_origins', ['*']);
        $methods = (string) $config->config('cors.allowed_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $headers = (string) $config->config('cors.allowed_headers', 'Content-Type, X-CSRF-Token, Authorization');

        $origin = $request->header('origin');
        $allow  = $this->resolveOrigin($origin, $origins);

        if ($request->isMethod('OPTIONS')) {
            $response = Response::make('', 204);
        } else {
            $response = $next($request);
        }

        if ($allow !== null) {
            $response
                ->withHeader('Access-Control-Allow-Origin', $allow)
                ->withHeader('Access-Control-Allow-Methods', $methods)
                ->withHeader('Access-Control-Allow-Headers', $headers)
                ->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    /** @param list<string>|array<int,string> $allowed */
    private function resolveOrigin(?string $origin, array $allowed): ?string
    {
        if (in_array('*', $allowed, true)) {
            return $origin ?? '*';
        }
        if ($origin !== null && in_array($origin, $allowed, true)) {
            return $origin;
        }

        return null;
    }
}
