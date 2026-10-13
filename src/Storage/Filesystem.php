<?php

declare(strict_types=1);

namespace BXCode\Storage;

/**
 * Storage contract. All paths are relative to the store's root; implementations
 * must reject any path that escapes it (path traversal). Cloud adapters (S3,
 * etc.) can implement this later without changing calling code.
 */
interface Filesystem
{
    /** File contents, or null when it does not exist. */
    public function get(string $path): ?string;

    public function put(string $path, string $contents): bool;

    public function append(string $path, string $contents): bool;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function size(string $path): int;

    public function makeDirectory(string $path): bool;

    /**
     * Files directly inside $directory, as paths relative to the store root.
     *
     * @return list<string>
     */
    public function files(string $directory = ''): array;

    /** The absolute filesystem path for $path (throws if it escapes the root). */
    public function path(string $path): string;
}
