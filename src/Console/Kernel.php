<?php

declare(strict_types=1);

namespace BXCode\Console;

use BXCode\Application;
use BXCode\Console\Commands\CacheClearCommand;
use BXCode\Console\Commands\MakeControllerCommand;
use BXCode\Console\Commands\MakeMiddlewareCommand;
use BXCode\Console\Commands\MakeMigrationCommand;
use BXCode\Console\Commands\MigrateCommand;
use BXCode\Console\Commands\MigrateRollbackCommand;
use BXCode\Console\Commands\RouteListCommand;
use BXCode\Console\Commands\ServeCommand;
use BXCode\Console\Commands\TestCommand;
use Throwable;

/**
 * The console application. Registers the built-in commands, parses argv into a
 * command name + positional args + --options, and dispatches. With no command
 * (or "list") it prints the available commands.
 *
 * No external console dependency — the framework stays installable as a plain
 * ZIP, and the CLI is never required to run the app in production.
 */
final class Kernel
{
    /** @var array<string,Command> name => command */
    private array $commands = [];

    /** @var list<class-string<Command>> */
    private const BUILTIN = [
        MakeControllerCommand::class,
        MakeMiddlewareCommand::class,
        MakeMigrationCommand::class,
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        RouteListCommand::class,
        CacheClearCommand::class,
        ServeCommand::class,
        TestCommand::class,
    ];

    public function __construct(
        private readonly Application $app,
        private readonly Output $output = new Output(),
    ) {
        foreach (self::BUILTIN as $class) {
            $this->register(new $class($this->app, $this->output));
        }
    }

    public function register(Command $command): void
    {
        $this->commands[$command::name()] = $command;
    }

    /**
     * Run from a raw argv list (without the script name).
     *
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[0] ?? 'list';

        if ($name === 'list' || $name === '--help' || $name === '-h') {
            $this->listCommands();

            return 0;
        }

        if (!isset($this->commands[$name])) {
            $this->output->error("Unknown command [{$name}].");
            $this->listCommands();

            return 1;
        }

        [$args, $options] = $this->parse(array_slice($argv, 1));

        try {
            return $this->commands[$name]->handle($args, $options);
        } catch (Throwable $e) {
            $this->output->error($e->getMessage());

            return 1;
        }
    }

    /**
     * @param list<string> $tokens
     * @return array{0:list<string>,1:array<string,string|bool>}
     */
    private function parse(array $tokens): array
    {
        $args    = [];
        $options = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $pair = substr($token, 2);
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    $options[$key] = $value;
                } else {
                    $options[$pair] = true;
                }
            } else {
                $args[] = $token;
            }
        }

        return [$args, $options];
    }

    private function listCommands(): void
    {
        $this->output->line('BXCode console');
        $this->output->line();
        $this->output->line('Usage: php bxcode <command> [arguments] [--options]');
        $this->output->line();

        $rows = [];
        foreach ($this->commands as $name => $command) {
            $rows[] = [$name, $command::description()];
        }
        sort($rows);

        $this->output->table(['Command', 'Description'], $rows);
    }
}
