<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

final class CacheClearCommand extends Command
{
    public static function name(): string
    {
        return 'cache:clear';
    }

    public static function description(): string
    {
        return 'Clear all entries from the application cache.';
    }

    public function handle(array $args, array $options): int
    {
        $this->app->cache()->flush();
        $this->info('Cache cleared.');

        return 0;
    }
}
