<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Application;
use BXCode\Database\Connection;
use BXCode\Database\QueryBuilder;

/**
 * Static entry point to the application's default database connection.
 *
 *   DB::table('users')->where('status', '=', 'active')->get();
 *   DB::transaction(fn () => ...);
 *
 * @method static QueryBuilder table(string $table)
 * @method static array select(string $sql, array $bindings = [])
 * @method static array|null selectOne(string $sql, array $bindings = [])
 * @method static int affectingStatement(string $sql, array $bindings = [])
 * @method static bool statement(string $sql, array $bindings = [])
 * @method static mixed transaction(\Closure $callback)
 * @method static Connection enableQueryLog()
 * @method static array queryLog()
 * @method static array slowQueries()
 */
final class DB
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return Application::getInstance()->db()->{$name}(...$arguments);
    }

    public static function connection(): Connection
    {
        return Application::getInstance()->db();
    }
}
