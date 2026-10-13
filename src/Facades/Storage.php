<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Application;
use BXCode\Storage\Filesystem;

/**
 * Static entry point to the default storage disk.
 *
 *   Storage::put('reports/april.csv', $csv);
 *   $data = Storage::get('reports/april.csv');
 *
 * @method static string|null get(string $path)
 * @method static bool put(string $path, string $contents)
 * @method static bool append(string $path, string $contents)
 * @method static bool exists(string $path)
 * @method static bool delete(string $path)
 * @method static int size(string $path)
 * @method static bool makeDirectory(string $path)
 * @method static array files(string $directory = '')
 * @method static string path(string $path)
 */
final class Storage
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return Application::getInstance()->storage()->{$name}(...$arguments);
    }

    public static function disk(): Filesystem
    {
        return Application::getInstance()->storage();
    }
}
