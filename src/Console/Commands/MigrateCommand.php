<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;
use BXCode\Database\Migrator;

final class MigrateCommand extends Command
{
    public static function name(): string
    {
        return 'migrate';
    }

    public static function description(): string
    {
        return 'Run any pending database migrations.';
    }

    public function handle(array $args, array $options): int
    {
        $migrator = new Migrator($this->app->db());
        $applied  = $migrator->run($this->app->basePath('database/migrations'));

        if ($applied === []) {
            $this->info('Nothing to migrate.');

            return 0;
        }

        foreach ($applied as $name) {
            $this->info("Migrated: {$name}");
        }

        return 0;
    }
}
