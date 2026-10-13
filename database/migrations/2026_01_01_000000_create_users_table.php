<?php

declare(strict_types=1);

use BXCode\Database\Connection;
use BXCode\Database\Migration;

return new class extends Migration {
    public function up(Connection $db): void
    {
        $autoId = $db->driver() === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $db->statement(
            "CREATE TABLE users (
                {$autoId},
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at VARCHAR(32) NULL
            )",
        );
    }

    public function down(Connection $db): void
    {
        $db->statement('DROP TABLE IF EXISTS users');
    }
};
