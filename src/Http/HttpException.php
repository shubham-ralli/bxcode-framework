<?php

declare(strict_types=1);

namespace BXCode\Http;

use RuntimeException;

/**
 * An exception that maps directly to an HTTP status. Throw it from anywhere to
 * produce a clean error response; the central handler renders it (JSON or HTML
 * by content negotiation) without leaking internals.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
        /** @var array<string,string> */
        public readonly array $headers = [],
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status));
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'Bad Request',
            401     => 'Unauthorized',
            403     => 'Forbidden',
            404     => 'Not Found',
            419     => 'Page Expired',
            422     => 'Unprocessable Entity',
            429     => 'Too Many Requests',
            default => 'Error',
        };
    }
}
