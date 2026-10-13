<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Database\Connection;
use BXCode\Facades\DB;
use PHPUnit\Framework\TestCase;

final class DbFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        $app = Application::boot(dirname(__DIR__, 2));

        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT)');
        $app->setConnection($connection);
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
    }

    public function test_facade_forwards_to_default_connection(): void
    {
        DB::table('users')->insert(['name' => 'Ada', 'status' => 'active']);

        self::assertSame(1, DB::table('users')->count());
        self::assertSame('Ada', DB::table('users')->where('status', '=', 'active')->value('name'));
    }

    public function test_facade_transaction(): void
    {
        $result = DB::transaction(function (): string {
            DB::table('users')->insert(['name' => 'Bob', 'status' => 'active']);

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(1, DB::table('users')->count());
    }
}
