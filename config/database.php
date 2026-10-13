<?php

declare(strict_types=1);

return [
    // Which connection below to use by default.
    'default' => env('DB_CONNECTION', 'mysql'),

    // Log every query (SQL + bindings + time). Defaults to app.debug via Application.
    'log_queries' => env('DB_LOG_QUERIES', null),

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'bxcode'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
        ],
    ],
];
