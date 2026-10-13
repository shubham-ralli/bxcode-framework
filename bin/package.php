<?php

declare(strict_types=1);

/*
 | Build a ready-to-upload production ZIP at dist/bxcode.zip.
 |
 | Run on your development machine (the target host needs no Composer or shell):
 |
 |   composer install --no-dev --optimize-autoloader
 |   php bin/package.php
 |   composer install        # restore dev dependencies afterwards
 |
 | The ZIP bundles vendor/, so it can be uploaded and extracted on a plain
 | shared host with no build step.
 */

$root = dirname(__DIR__);
$out  = $root . '/dist/bxcode.zip';

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The zip extension (ZipArchive) is required to build the package.\n");
    exit(1);
}

// Warn if dev dependencies are still installed — they bloat the package.
if (is_file($root . '/vendor/bin/phpunit')) {
    fwrite(STDOUT, "Warning: dev dependencies are present. For a lean package run:\n");
    fwrite(STDOUT, "  composer install --no-dev --optimize-autoloader\n\n");
}

/** Top-level paths included in the package. */
$include = [
    'src', 'config', 'routes', 'resources', 'public', 'database', 'vendor',
    'bxcode', 'composer.json', '.env.example', 'README.md', 'CHANGELOG.md',
];

/** Empty directories to create (so storage exists and stays writable). */
$emptyDirs = ['storage/logs', 'storage/cache', 'storage/app'];

/** Path fragments to skip anywhere in the tree. */
$skip = ['/.git/', '/.DS_Store', '.phpunit.cache', '.tmp'];

@mkdir($root . '/dist', 0775, true);
if (is_file($out)) {
    unlink($out);
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create {$out}.\n");
    exit(1);
}

$count = 0;
$add = static function (string $absolute, string $relative) use ($zip, $skip, &$count): void {
    foreach ($skip as $fragment) {
        if (str_contains('/' . $relative, $fragment)) {
            return;
        }
    }
    $zip->addFile($absolute, $relative);
    $count++;
};

foreach ($include as $entry) {
    $path = $root . '/' . $entry;

    if (is_file($path)) {
        $add($path, $entry);
        continue;
    }

    if (!is_dir($path)) {
        fwrite(STDOUT, "Skipping missing: {$entry}\n");
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile()) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $add($file->getPathname(), $relative);
        }
    }
}

foreach ($emptyDirs as $dir) {
    $zip->addEmptyDir($dir);
    $zip->addFromString($dir . '/.gitkeep', '');
}

$zip->close();

printf("Built %s (%d files).\n", $out, $count);
