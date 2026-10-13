<?php

declare(strict_types=1);

namespace BXCode\Console;

use BXCode\Application;

/**
 * Base console command. Subclasses declare a name and description and implement
 * handle(). They receive the Application and an Output, and return an exit code
 * (0 = success).
 */
abstract class Command
{
    public function __construct(
        protected readonly Application $app,
        protected readonly Output $output,
    ) {
    }

    /** The command name, e.g. "make:controller". */
    abstract public static function name(): string;

    abstract public static function description(): string;

    /**
     * @param list<string>         $args    positional arguments
     * @param array<string,string|bool> $options parsed --flags
     */
    abstract public function handle(array $args, array $options): int;

    protected function line(string $text = ''): void
    {
        $this->output->line($text);
    }

    protected function info(string $text): void
    {
        $this->output->info($text);
    }

    protected function error(string $text): void
    {
        $this->output->error($text);
    }
}
