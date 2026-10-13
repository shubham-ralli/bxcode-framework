<?php

declare(strict_types=1);

namespace BXCode\Tests\Fixtures;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/** Tags the response with a header so tests can assert it ran, and in what order. */
final class AppendHeaderMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $trail = $response->header('x-trail');
        $trail = $trail === null ? 'A' : $trail . ',A';

        return $response->withHeader('x-trail', $trail);
    }
}
