<?php

declare(strict_types=1);

namespace BXCode\Cache;

use Closure;

/**
 * Filesystem cache. Each entry is a file holding an expiry timestamp and the
 * serialized value; expired entries are treated as missing and cleaned up on
 * read. Writes are atomic (temp file + rename) so a concurrent reader never
 * sees a half-written entry.
 *
 * A key prefix provides simple namespacing: two FileStores sharing a directory
 * but using different prefixes never collide.
 */
final class FileStore implements Store
{
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = '',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }

        /** @var array{expires:int,value:mixed}|false $entry */
        $entry = @unserialize($raw);
        if (!is_array($entry) || !array_key_exists('expires', $entry)) {
            @unlink($path); // corrupt entry
            return $default;
        }

        if ($entry['expires'] !== 0 && $entry['expires'] <= time()) {
            @unlink($path); // expired
            return $default;
        }

        return $entry['value'];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = ($ttl === null || $ttl <= 0) ? 0 : time() + $ttl;
        $payload = serialize(['expires' => $expires, 'value' => $value]);

        return $this->write($this->path($key), $payload);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, null);
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this->miss()) !== $this->miss();
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);

        return !is_file($path) || @unlink($path);
    }

    public function flush(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $ok = true;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $ok = ($file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname())) && $ok;
        }

        return $ok;
    }

    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        $sentinel = $this->miss();
        $value    = $this->get($key, $sentinel);

        if ($value !== $sentinel) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /** A unique object used to distinguish "cached null" from "not cached". */
    private function miss(): object
    {
        static $miss;

        return $miss ??= new \stdClass();
    }

    /** Hash the (prefixed) key into a two-level directory path. */
    private function path(string $key): string
    {
        $hash = sha1($this->prefix . ':' . $key);

        return $this->directory . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2);
    }

    private function write(string $path, string $contents): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }
}
