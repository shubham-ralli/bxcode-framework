<?php

declare(strict_types=1);

namespace BXCode\Http;

/**
 * HTTP response. Build one with the static factories (html/json/redirect/make),
 * tweak it fluently, then send() it. send() is the only method that touches
 * global output, which keeps it testable everywhere else.
 */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public static function make(string $body = '', int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['content-type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['content-type' => 'text/plain; charset=UTF-8']);
    }

    /** @param mixed $data Anything json_encode can handle. */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body   = json_encode(['error' => 'Response could not be encoded as JSON.']) ?: '{}';
            $status = 500;
        }

        return new self($body, $status, ['content-type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['location' => $location]);
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /** Emit status, headers, and body. The one method with global side effects. */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }
}
