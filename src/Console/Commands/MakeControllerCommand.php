<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

final class MakeControllerCommand extends GeneratorCommand
{
    public static function name(): string
    {
        return 'make:controller';
    }

    public static function description(): string
    {
        return 'Create a new controller class in app/Controllers.';
    }

    public function handle(array $args, array $options): int
    {
        if (!isset($args[0])) {
            $this->error('Usage: php bxcode make:controller <Name>');

            return 1;
        }

        $stub = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use BXCode\Http\Request;
        use BXCode\Http\Response;

        final class {{class}}
        {
            public function index(Request $request): Response
            {
                return Response::html('<h1>{{class}}</h1>');
            }
        }

        PHP;

        return $this->writeClass('app/Controllers', $args[0], $stub);
    }
}
