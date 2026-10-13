<?php

declare(strict_types=1);

use BXCode\Application;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = Application::boot($root);

require $root . '/routes/web.php';

$app->run();
