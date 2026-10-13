<?php

declare(strict_types=1);

namespace BXCode\Tests\Fixtures;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

/** Returns early without calling $next — the route handler must never run. */
final class ShortCircuitMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return Response::text('blocked', 403);
    }
}
