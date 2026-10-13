<?php

declare(strict_types=1);

use BXCode\Application;

$root = dirname(__DIR__);

// Use Composer's autoloader when installed; otherwise the built-in one, so the
// app runs with no Composer and no vendor/ directory (upload-and-go hosting).
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    require $root . '/bootstrap/autoload.php';
}

$app = Application::boot($root);

require $root . '/routes/web.php';

$app->run();
