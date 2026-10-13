<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Database\Connection;
use BXCode\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private Connection $db;

    private string $dir;

    protected function setUp(): void
    {
        $this->db  = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->dir = dirname(__DIR__, 2) . '/database/migrations';
    }

    public function test_run_applies_migrations_then_is_idempotent(): void
    {
        $migrator = new Migrator($this->db);

        $first = $migrator->run($this->dir);
        self::assertContains('2026_01_01_000000_create_users_table', $first);

        // Table now exists and is usable.
        $this->db->table('users')->insert(['name' => 'Ada', 'email' => 'ada@x.io', 'status' => 'active']);
        self::assertSame(1, $this->db->table('users')->count());

        // Running again applies nothing.
        self::assertSame([], $migrator->run($this->dir));
    }

    public function test_rollback_reverses_latest_batch(): void
    {
        $migrator = new Migrator($this->db);
        $migrator->run($this->dir);

        $rolledBack = $migrator->rollback($this->dir);
        self::assertContains('2026_01_01_000000_create_users_table', $rolledBack);
        self::assertSame([], $migrator->appliedNames());

        // The users table is gone, so a query against it must fail.
        $this->expectException(\PDOException::class);
        $this->db->table('users')->count();
    }
}
