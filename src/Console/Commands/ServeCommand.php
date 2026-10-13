<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

final class ServeCommand extends Command
{
    public static function name(): string
    {
        return 'serve';
    }

    public static function description(): string
    {
        return 'Start the PHP development server (--host, --port).';
    }

    public function handle(array $args, array $options): int
    {
        $host = is_string($options['host'] ?? null) ? $options['host'] : '127.0.0.1';
        $port = is_string($options['port'] ?? null) ? $options['port'] : '8000';

        $public = $this->app->basePath('public');

        $this->info("BXCode development server started: http://{$host}:{$port}");
        $this->info('Press Ctrl+C to stop.');

        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($public),
        );

        passthru($command, $status);

        return $status;
    }
}
