<?php

declare(strict_types=1);

namespace BXCode\Console\Commands;

use BXCode\Console\Command;

/**
 * Shared behaviour for `make:*` commands: validate a class name and write a stub
 * into the application, refusing to overwrite an existing file.
 */
abstract class GeneratorCommand extends Command
{
    /**
     * Write $stub (with {{class}} replaced) to app/<relativeDir>/<Class>.php.
     */
    protected function writeClass(string $relativeDir, string $class, string $stub): int
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1) {
            $this->error("Invalid class name [{$class}].");

            return 1;
        }

        $dir  = $this->app->basePath($relativeDir);
        $path = $dir . '/' . $class . '.php';

        if (is_file($path)) {
            $this->error("File already exists: {$relativeDir}/{$class}.php");

            return 1;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("Could not create directory: {$relativeDir}");

            return 1;
        }

        file_put_contents($path, str_replace('{{class}}', $class, $stub));
        $this->info("Created: {$relativeDir}/{$class}.php");

        return 0;
    }
}
