<?php

declare(strict_types=1);

namespace BXCode\Cache;

use Closure;

/**
 * Cache contract. Implementations persist values under string keys with an
 * optional TTL. Redis and other backends can implement this later without any
 * change to calling code.
 */
interface Store
{
    /** The cached value for $key, or $default when missing or expired. */
    public function get(string $key, mixed $default = null): mixed;

    /** Store $value under $key for $ttl seconds (null = forever). */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    /** Whether a live (unexpired) value exists for $key. */
    public function has(string $key): bool;

    /** Remove $key. */
    public function forget(string $key): bool;

    /** Remove everything in this store. */
    public function flush(): bool;

    /** Store with no expiry. */
    public function forever(string $key, mixed $value): bool;

    /**
     * Return the cached value, or compute it with $callback, store it for $ttl,
     * and return it.
     *
     * @template T
     * @param Closure():T $callback
     * @return T
     */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed;
}
