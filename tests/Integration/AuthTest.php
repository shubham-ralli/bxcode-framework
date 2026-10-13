<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Auth\ArrayUserProvider;
use BXCode\Auth\DatabaseUserProvider;
use BXCode\Auth\SessionGuard;
use BXCode\Database\Connection;
use BXCode\Security\Hash;
use BXCode\Security\Session;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private Hash $hash;

    protected function setUp(): void
    {
        $this->hash = new Hash();
    }

    private function arrayGuard(Session $session): SessionGuard
    {
        $provider = new ArrayUserProvider([
            ['id' => 1, 'email' => 'ada@x.io', 'password' => $this->hash->make('secret')],
        ], $this->hash);

        return new SessionGuard($provider, $session);
    }

    public function test_attempt_fails_with_wrong_password(): void
    {
        $guard = $this->arrayGuard(new Session());

        self::assertFalse($guard->attempt(['email' => 'ada@x.io', 'password' => 'nope']));
        self::assertFalse($guard->check());
        self::assertTrue($guard->guest());
    }

    public function test_attempt_succeeds_logs_in_and_regenerates_session(): void
    {
        $session = new Session();
        $session->start();
        $guard = $this->arrayGuard($session);

        self::assertTrue($guard->attempt(['email' => 'ada@x.io', 'password' => 'secret']));
        self::assertTrue($guard->check());
        self::assertSame(1, $guard->id());
        self::assertNotNull($guard->user());
        self::assertSame(1, $guard->user()?->getAuthId());
        self::assertTrue($session->regenerated, 'session id must regenerate on login');
    }

    public function test_user_is_restored_from_session_by_a_fresh_guard(): void
    {
        $session = new Session();
        $session->start();

        $this->arrayGuard($session)->attempt(['email' => 'ada@x.io', 'password' => 'secret']);

        // A new guard sharing the session should resolve the user from the id.
        $fresh = $this->arrayGuard($session);
        self::assertTrue($fresh->check());
        self::assertSame(1, $fresh->id());
    }

    public function test_logout_clears_authentication(): void
    {
        $session = new Session();
        $session->start();
        $guard = $this->arrayGuard($session);
        $guard->attempt(['email' => 'ada@x.io', 'password' => 'secret']);

        $guard->logout();

        self::assertFalse($guard->check());
        self::assertNull($guard->id());
    }

    public function test_database_user_provider(): void
    {
        $db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, password TEXT)');
        $db->table('users')->insert(['email' => 'bob@x.io', 'password' => $this->hash->make('pw')]);

        $provider = new DatabaseUserProvider($db, $this->hash);
        $session  = new Session();
        $session->start();
        $guard = new SessionGuard($provider, $session);

        self::assertFalse($guard->attempt(['email' => 'bob@x.io', 'password' => 'bad']));
        self::assertTrue($guard->attempt(['email' => 'bob@x.io', 'password' => 'pw']));
        self::assertSame(1, $guard->id());
    }
}
