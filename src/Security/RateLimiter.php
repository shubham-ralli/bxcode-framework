<?php

declare(strict_types=1);

namespace BXCode\Security;

use BXCode\Cache\Store;

/**
 * Rate-limiting primitive backed by the cache. Counts hits per key within a
 * decay window.
 *
 * ponytail: get+put is not atomic, so under heavy concurrency a few requests
 * may slip past the limit on the same key. That is acceptable for shared
 * hosting; swap in an atomic backend (e.g. Redis INCR) for strict guarantees.
 */
final class RateLimiter
{
    public function __construct(private readonly Store $cache)
    {
    }

    /** Record a hit and return the new attempt count. */
    public function hit(string $key, int $decaySeconds): int
    {
        $attempts = $this->attempts($key) + 1;
        $this->cache->put($this->key($key), $attempts, $decaySeconds);

        return $attempts;
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get($this->key($key), 0);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    public function clear(string $key): void
    {
        $this->cache->forget($this->key($key));
    }

    private function key(string $key): string
    {
        return 'ratelimit:' . sha1($key);
    }
}
