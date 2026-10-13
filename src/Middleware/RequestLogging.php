<?php

declare(strict_types=1);

namespace BXCode\Middleware;

use BXCode\Application;
use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/**
 * Logs each request as "METHOD /path -> status" through the application logger,
 * tagged with the logger's per-request id.
 */
final class RequestLogging implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        Application::getInstance()->logger()->info(
            $request->method() . ' ' . $request->path() . ' -> ' . $response->status(),
            ['ip' => $request->ip()],
        );

        return $response;
    }
}
