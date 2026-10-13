<?php

declare(strict_types=1);

namespace BXCode\Http;

/**
 * HTTP request. Wraps the superglobals so controllers never touch them directly.
 *
 * Build one from the live request with Request::capture(), or construct manually
 * (handy in tests).
 */
final class Request
{
    /** @var array<string,string> Route parameters, filled by the Router after a match. */
    private array $params = [];

    /**
     * @param array<string,mixed>  $query   Query-string values ($_GET).
     * @param array<string,mixed>  $body    Parsed request body ($_POST or JSON).
     * @param array<string,string> $headers Normalised request headers (lowercase keys).
     * @param array<string,mixed>  $server  Server values ($_SERVER).
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
        private readonly array $server = [],
    ) {
    }

    /** Build a Request from the current PHP superglobals. */
    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = '/' . trim($path, '/');

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            body: self::parseBody(),
            headers: self::captureHeaders(),
            server: $_SERVER,
        );
    }

    /** @return array<string,mixed> */
    private static function parseBody(): array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    /** @return array<string,string> */
    private static function captureHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** A single query-string value, or $default when absent. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** A single body value, or $default when absent. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * All input, body merged over query (body wins on key collision).
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** A request header by name (case-insensitive), or $default when absent. */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * The client IP from REMOTE_ADDR. Proxy headers (X-Forwarded-For) are NOT
     * trusted by default — they are spoofable unless the proxy is verified.
     */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    /** A single route parameter captured from the matched path. */
    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    /** @return array<string,string> */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Return a copy carrying the given route parameters.
     *
     * @param array<string,string> $params
     */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Whether the client prefers a JSON response: an Accept or Content-Type
     * header mentioning JSON, or a path under /api.
     */
    public function wantsJson(): bool
    {
        $accept = strtolower($this->header('accept', '') ?? '');
        $type   = strtolower($this->header('content-type', '') ?? '');

        return str_contains($accept, 'application/json')
            || str_contains($type, 'application/json')
            || str_starts_with($this->path, '/api/')
            || $this->path === '/api';
    }
}
