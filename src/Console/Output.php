<?php

declare(strict_types=1);

namespace BXCode\Console;

/**
 * Console output. Writes to a stream (STDOUT by default); tests pass a memory
 * stream to capture what a command prints. Plain text — no colour — so output
 * is stable and easy to assert.
 */
final class Output
{
    /** @param resource $stream */
    public function __construct(private $stream = STDOUT)
    {
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function line(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function info(string $text): void
    {
        $this->line($text);
    }

    public function error(string $text): void
    {
        $this->line('Error: ' . $text);
    }

    /**
     * Print rows as an aligned table.
     *
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($cell));
            }
        }

        $render = function (array $cells) use ($widths): string {
            $out = [];
            foreach ($cells as $i => $cell) {
                $out[] = str_pad($cell, $widths[$i] ?? 0);
            }

            return '  ' . implode('  ', $out);
        };

        $this->line($render($headers));
        $this->line($render(array_map(static fn (int $w): string => str_repeat('-', $w), $widths)));
        foreach ($rows as $row) {
            $this->line($render($row));
        }
    }
}
