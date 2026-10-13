<?php

declare(strict_types=1);

/*
 | Native PSR-4 autoloader — no Composer required.
 |
 | This lets BXCode run CodeIgniter-3 style: upload the files and go, with no
 | `composer install` and no `vendor/` directory. The production front
 | controller uses Composer's autoloader when present and falls back to this
 | one otherwise, so both workflows work unchanged.
 */

spl_autoload_register(static function (string $class): void {
    /** @var array<string,string> $prefixes namespace prefix => base directory */
    static $prefixes = [
        'BXCode\\' => __DIR__ . '/../src/',
        'App\\'    => __DIR__ . '/../app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }

        $relative = substr($class, $length);
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

// Global helpers (env/config/view/...), loaded the same way Composer's "files" would.
require __DIR__ . '/../src/helpers.php';
