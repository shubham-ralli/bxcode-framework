<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

final class MakeMiddlewareCommand extends GeneratorCommand
{
    public static function name(): string
    {
        return 'make:middleware';
    }

    public static function description(): string
    {
        return 'Create a new middleware class in app/Middleware.';
    }

    public function handle(array $args, array $options): int
    {
        if (!isset($args[0])) {
            $this->error('Usage: php bxcode make:middleware <Name>');

            return 1;
        }

        $stub = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Middleware;

        use BXCode\Http\Middleware;
        use BXCode\Http\Request;
        use BXCode\Http\Response;
        use Closure;

        final class {{class}} implements Middleware
        {
            public function handle(Request $request, Closure $next): Response
            {
                // ... before ...

                $response = $next($request);

                // ... after ...

                return $response;
            }
        }

        PHP;

        return $this->writeClass('app/Middleware', $args[0], $stub);
    }
}
