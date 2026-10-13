<?php

declare(strict_types=1);

namespace BXCode\Storage;

/**
 * Local-disk storage rooted at a base directory. Every path is normalised and
 * checked to stay within the root, so "../" traversal and absolute paths can
 * never read or write outside the store.
 */
final class LocalFilesystem implements Filesystem
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function get(string $path): ?string
    {
        $full = $this->path($path);
        if (!is_file($full)) {
            return null;
        }
        $contents = @file_get_contents($full);

        return $contents === false ? null : $contents;
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->path($path);
        $dir  = dirname($full);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return @file_put_contents($full, $contents, LOCK_EX) !== false;
    }

    public function append(string $path, string $contents): bool
    {
        $full = $this->path($path);
        if (!is_dir(dirname($full))) {
            return $this->put($path, $contents);
        }

        return @file_put_contents($full, $contents, FILE_APPEND | LOCK_EX) !== false;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function delete(string $path): bool
    {
        $full = $this->path($path);

        return !is_file($full) || @unlink($full);
    }

    public function size(string $path): int
    {
        $full = $this->path($path);

        return is_file($full) ? (int) filesize($full) : 0;
    }

    public function makeDirectory(string $path): bool
    {
        $full = $this->path($path);

        return is_dir($full) || @mkdir($full, 0775, true);
    }

    public function files(string $directory = ''): array
    {
        $full = $this->path($directory);
        if (!is_dir($full)) {
            return [];
        }

        $files = [];
        foreach (scandir($full) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_file($full . '/' . $entry)) {
                $relative = ltrim(($directory === '' ? '' : rtrim($directory, '/') . '/') . $entry, '/');
                $files[]  = $relative;
            }
        }
        sort($files);

        return $files;
    }

    /** Resolve and validate a relative path, rejecting any escape from the root. */
    public function path(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Path contains a null byte.');
        }

        $combined   = $this->root . '/' . str_replace('\\', '/', $path);
        $normalised = $this->normalise($combined);

        if ($normalised !== $this->root && !str_starts_with($normalised, $this->root . '/')) {
            throw new \InvalidArgumentException("Path [{$path}] escapes the storage root.");
        }

        return $normalised;
    }

    /** Collapse "." and ".." segments without touching the filesystem. */
    private function normalise(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $segments   = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return ($isAbsolute ? '/' : '') . implode('/', $segments);
    }
}
