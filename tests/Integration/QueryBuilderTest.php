<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Database\Connection;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, status TEXT)',
        );
        $this->db->table('users')->insert(['name' => 'Ada', 'email' => 'ada@x.io', 'status' => 'active']);
        $this->db->table('users')->insert(['name' => 'Bob', 'email' => 'bob@x.io', 'status' => 'active']);
        $this->db->table('users')->insert(['name' => 'Cleo', 'email' => 'cleo@x.io', 'status' => 'banned']);
    }

    public function test_get_with_where_and_limit(): void
    {
        $rows = $this->db->table('users')
            ->where('status', '=', 'active')
            ->orderBy('name', 'asc')
            ->limit(1)
            ->get();

        self::assertCount(1, $rows);
        self::assertSame('Ada', $rows[0]['name']);
    }

    public function test_first_and_value(): void
    {
        $first = $this->db->table('users')->where('email', '=', 'bob@x.io')->first();
        self::assertSame('Bob', $first['name']);

        $name = $this->db->table('users')->where('id', '=', 3)->value('name');
        self::assertSame('Cleo', $name);
    }

    public function test_where_in(): void
    {
        $rows = $this->db->table('users')->whereIn('id', [1, 3])->orderBy('id')->get();

        self::assertSame(['Ada', 'Cleo'], array_column($rows, 'name'));
    }

    public function test_where_in_empty_matches_nothing(): void
    {
        self::assertSame([], $this->db->table('users')->whereIn('id', [])->get());
    }

    public function test_count_and_exists(): void
    {
        self::assertSame(2, $this->db->table('users')->where('status', '=', 'active')->count());
        self::assertTrue($this->db->table('users')->where('status', '=', 'banned')->exists());
        self::assertFalse($this->db->table('users')->where('status', '=', 'ghost')->exists());
    }

    public function test_insert_get_id_update_delete(): void
    {
        $id = $this->db->table('users')->insertGetId(['name' => 'Dev', 'email' => 'dev@x.io', 'status' => 'active']);
        self::assertSame(4, $id);

        $affected = $this->db->table('users')->where('id', '=', $id)->update(['status' => 'banned']);
        self::assertSame(1, $affected);
        self::assertSame('banned', $this->db->table('users')->where('id', '=', $id)->value('status'));

        $deleted = $this->db->table('users')->where('id', '=', $id)->delete();
        self::assertSame(1, $deleted);
        self::assertFalse($this->db->table('users')->where('id', '=', $id)->exists());
    }

    public function test_paginate_returns_page_and_metadata(): void
    {
        $page = $this->db->table('users')->orderBy('id')->paginate(perPage: 2, page: 1);

        self::assertCount(2, $page['data']);
        self::assertSame(3, $page['total']);
        self::assertSame(2, $page['per_page']);
        self::assertSame(1, $page['current_page']);
        self::assertSame(2, $page['last_page']);
    }

    public function test_values_are_bound_not_interpolated(): void
    {
        // A would-be injection payload is treated as a literal value, matching nothing.
        $rows = $this->db->table('users')->where('name', '=', "Ada' OR '1'='1")->get();

        self::assertSame([], $rows);
    }

    public function test_invalid_identifier_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->table('users')->where('name; DROP TABLE users', '=', 'x')->get();
    }

    public function test_invalid_operator_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->table('users')->where('name', 'INJECT', 'x')->get();
    }
}
