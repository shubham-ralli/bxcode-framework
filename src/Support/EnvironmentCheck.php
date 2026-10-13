<?php

declare(strict_types=1);

namespace BXCode\Support;

/**
 * Checks that the host meets BXCode's runtime requirements: PHP version, the
 * required extensions, and a writable storage directory. Pure logic (no output),
 * so it is testable and reusable from both the browser checker and the CLI.
 */
final class EnvironmentCheck
{
    public const MIN_PHP = '8.3.0';

    /** Extensions BXCode needs to boot and serve requests. */
    private const REQUIRED = ['json', 'mbstring', 'PDO'];

    /** Extensions needed only for specific features (MySQL). */
    private const RECOMMENDED = ['pdo_mysql'];

    /**
     * @return array{pass:bool,checks:list<array{name:string,ok:bool,required:bool,detail:string}>}
     */
    public function run(string $storagePath): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, self::MIN_PHP, '>=');
        $checks[] = [
            'name'     => 'PHP version',
            'ok'       => $phpOk,
            'required' => true,
            'detail'   => PHP_VERSION . ' (need ' . self::MIN_PHP . '+)',
        ];

        foreach (self::REQUIRED as $ext) {
            $checks[] = [
                'name'     => "ext-{$ext}",
                'ok'       => extension_loaded($ext),
                'required' => true,
                'detail'   => extension_loaded($ext) ? 'loaded' : 'missing',
            ];
        }

        foreach (self::RECOMMENDED as $ext) {
            $checks[] = [
                'name'     => "ext-{$ext}",
                'ok'       => extension_loaded($ext),
                'required' => false,
                'detail'   => extension_loaded($ext) ? 'loaded' : 'missing (needed for MySQL)',
            ];
        }

        $writable = is_dir($storagePath) && is_writable($storagePath);
        $checks[] = [
            'name'     => 'storage writable',
            'ok'       => $writable,
            'required' => true,
            'detail'   => $writable ? $storagePath : "not writable: {$storagePath}",
        ];

        // Only required checks determine the overall pass/fail.
        $pass = true;
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                $pass = false;
            }
        }

        return ['pass' => $pass, 'checks' => $checks];
    }
}
