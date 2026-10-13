<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

final class MakeMigrationCommand extends Command
{
    public static function name(): string
    {
        return 'make:migration';
    }

    public static function description(): string
    {
        return 'Create a new timestamped migration file in database/migrations.';
    }

    public function handle(array $args, array $options): int
    {
        if (!isset($args[0])) {
            $this->error('Usage: php bxcode make:migration <name>  (e.g. create_users_table)');

            return 1;
        }

        $name = $this->snake($args[0]);
        if ($name === '') {
            $this->error('Invalid migration name.');

            return 1;
        }

        $dir = $this->app->basePath('database/migrations');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error('Could not create database/migrations.');

            return 1;
        }

        $file = gmdate('Y_m_d_His') . '_' . $name . '.php';
        $path = $dir . '/' . $file;

        $stub = <<<'PHP'
        <?php

        declare(strict_types=1);

        use BXCode\Database\Connection;
        use BXCode\Database\Migration;

        return new class extends Migration {
            public function up(Connection $db): void
            {
                // $db->statement('CREATE TABLE example (...)');
            }

            public function down(Connection $db): void
            {
                // $db->statement('DROP TABLE IF EXISTS example');
            }
        };

        PHP;

        file_put_contents($path, $stub);
        $this->info("Created: database/migrations/{$file}");

        return 0;
    }

    private function snake(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
