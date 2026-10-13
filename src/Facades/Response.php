<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Http\Response as HttpResponse;

/**
 * Convenience facade so routes can `use BXCode\Facades\Response;` and call the
 * same factories that live on BXCode\Http\Response. Pure forwarding — no state.
 *
 * @method static HttpResponse make(string $body = '', int $status = 200, array $headers = [])
 * @method static HttpResponse html(string $html, int $status = 200)
 * @method static HttpResponse text(string $text, int $status = 200)
 * @method static HttpResponse json(mixed $data, int $status = 200)
 * @method static HttpResponse redirect(string $location, int $status = 302)
 */
final class Response
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): HttpResponse
    {
        return HttpResponse::{$name}(...$arguments);
    }
}
