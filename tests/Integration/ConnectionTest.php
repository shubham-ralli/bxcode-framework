<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Database\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->statement('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, v TEXT)');
    }

    public function test_transaction_commits_on_success(): void
    {
        $this->db->transaction(function (): void {
            $this->db->table('t')->insert(['v' => 'a']);
            $this->db->table('t')->insert(['v' => 'b']);
        });

        self::assertSame(2, $this->db->table('t')->count());
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->db->table('t')->insert(['v' => 'a']);
                throw new RuntimeException('boom');
            });
            self::fail('Expected exception did not propagate.');
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame(0, $this->db->table('t')->count());
    }

    public function test_query_log_records_sql_and_bindings(): void
    {
        $this->db->enableQueryLog();
        $this->db->table('t')->where('v', '=', 'x')->get();

        $log = $this->db->queryLog();
        self::assertNotEmpty($log);
        $last = end($log);
        self::assertStringContainsString('SELECT', $last['sql']);
        self::assertSame(['x'], $last['bindings']);
        self::assertArrayHasKey('time_ms', $last);
    }

    public function test_slow_queries_filters_by_threshold(): void
    {
        $this->db->enableQueryLog();
        $this->db->slowQueryMs = 0.0; // everything counts as slow
        $this->db->table('t')->get();

        self::assertNotEmpty($this->db->slowQueries());
    }

    public function test_unsupported_driver_throws(): void
    {
        $db = new Connection(['driver' => 'oracle']);
        $this->expectException(\InvalidArgumentException::class);
        $db->pdo();
    }
}
