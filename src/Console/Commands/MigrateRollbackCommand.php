<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;
use BXCode\Database\Migrator;

final class MigrateRollbackCommand extends Command
{
    public static function name(): string
    {
        return 'migrate:rollback';
    }

    public static function description(): string
    {
        return 'Roll back the most recent batch of migrations.';
    }

    public function handle(array $args, array $options): int
    {
        $migrator   = new Migrator($this->app->db());
        $rolledBack = $migrator->rollback($this->app->basePath('database/migrations'));

        if ($rolledBack === []) {
            $this->info('Nothing to roll back.');

            return 0;
        }

        foreach ($rolledBack as $name) {
            $this->info("Rolled back: {$name}");
        }

        return 0;
    }
}
