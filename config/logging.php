<?php

declare(strict_types=1);

return [
    // Minimum level to record: debug | info | warning | error.
    'level' => env('LOG_LEVEL', 'debug'),

    // Log file path. Defaults to storage/logs/app.log via Application when unset.
    'path' => env('LOG_PATH', null),
];
