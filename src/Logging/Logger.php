<?php

declare(strict_types=1);

namespace BXCode\Logging;

/**
 * A small file logger with level filtering, a per-request id, and automatic
 * redaction of secret-looking context keys. No external dependency.
 *
 *   $log = new Logger('/path/storage/app.log', minLevel: 'info');
 *   $log->error('Payment failed', ['user' => 7, 'password' => 'hunter2']);
 *   // password is written as [redacted]
 */
final class Logger
{
    /** @var array<string,int> */
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private const REDACT = ['password', 'secret', 'token', 'authorization', 'api_key', 'apikey'];

    private readonly string $requestId;

    private readonly int $threshold;

    public function __construct(
        private readonly string $file,
        string $minLevel = 'debug',
        ?string $requestId = null,
    ) {
        $this->threshold = self::LEVELS[$minLevel] ?? 0;
        $this->requestId = $requestId ?? bin2hex(random_bytes(6));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->threshold) {
            return;
        }

        $line = sprintf(
            '[%s] [%s] [%s] %s',
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $this->requestId,
            $message,
        );

        if ($context !== []) {
            $line .= ' ' . json_encode($this->redact($context), JSON_UNESCAPED_SLASHES);
        }

        $this->write($line . PHP_EOL);
    }

    /**
     * Replace secret-looking values with [redacted], recursively.
     *
     * @param array<array-key,mixed> $context
     * @return array<array-key,mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACT, true)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }

    private function write(string $line): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
