<?php

declare(strict_types=1);

namespace BXCode\Tests\Fixtures;

use BXCode\Http\Request;
use BXCode\Http\Response;

final class GreetController
{
    public function show(Request $request, string $name): Response
    {
        return Response::json([
            'name'   => $name,
            'method' => $request->method(),
        ]);
    }
}
