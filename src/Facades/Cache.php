<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Application;
use BXCode\Cache\Store;

/**
 * Static entry point to the default cache store.
 *
 *   Cache::put('key', $value, 3600);
 *   Cache::remember('users', 600, fn () => DB::table('users')->get());
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, ?int $ttl = null)
 * @method static bool has(string $key)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static bool forever(string $key, mixed $value)
 * @method static mixed remember(string $key, ?int $ttl, \Closure $callback)
 */
final class Cache
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return Application::getInstance()->cache()->{$name}(...$arguments);
    }

    public static function store(): Store
    {
        return Application::getInstance()->cache();
    }
}
