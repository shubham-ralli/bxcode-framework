<?php

declare(strict_types=1);

/*
 | Database settings. Edit the values directly — there is no .env file.
 | Put your real host / database / username / password below.
 */

return [
    // Which connection below to use by default: 'mysql' or 'sqlite'.
    'default' => 'mysql',

    // Log every query (SQL + bindings + time). null = follow app.debug.
    'log_queries' => null,

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'bxcode',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => __DIR__ . '/../storage/database.sqlite', // or ':memory:'
        ],
    ],
];
