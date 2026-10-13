<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

final class TestCommand extends Command
{
    public static function name(): string
    {
        return 'test';
    }

    public static function description(): string
    {
        return 'Run the test suite via PHPUnit.';
    }

    public function handle(array $args, array $options): int
    {
        $phpunit = $this->app->basePath('vendor/bin/phpunit');

        if (!is_file($phpunit)) {
            $this->error('PHPUnit is not installed. Run "composer install" first.');

            return 1;
        }

        passthru(escapeshellarg($phpunit), $status);

        return $status;
    }
}
