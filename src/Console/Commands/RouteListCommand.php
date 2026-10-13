<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

final class RouteListCommand extends Command
{
    public static function name(): string
    {
        return 'route:list';
    }

    public static function description(): string
    {
        return 'List the application routes.';
    }

    public function handle(array $args, array $options): int
    {
        // Load the application's route definitions if they are not already loaded.
        $routesFile = $this->app->basePath('routes/web.php');
        if ($this->app->router()->all() === [] && is_file($routesFile)) {
            require $routesFile;
        }

        $routes = $this->app->router()->all();

        if ($routes === []) {
            $this->info('No routes registered.');

            return 0;
        }

        $rows = array_map(
            static fn (array $r): array => [
                $r['method'],
                $r['path'],
                $r['name'] ?? '',
                (string) $r['middleware'],
            ],
            $routes,
        );

        $this->output->table(['Method', 'Path', 'Name', 'Middleware'], $rows);

        return 0;
    }
}
