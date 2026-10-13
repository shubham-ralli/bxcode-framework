<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Security\Csrf;
use BXCode\Security\Hash;
use BXCode\Security\Session;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function test_session_get_set_has_forget(): void
    {
        $session = new Session();
        $session->start();

        self::assertFalse($session->has('name'));
        $session->set('name', 'Ada');
        self::assertTrue($session->has('name'));
        self::assertSame('Ada', $session->get('name'));

        $session->forget('name');
        self::assertFalse($session->has('name'));
        self::assertSame('x', $session->get('name', 'x'));
    }

    public function test_session_flash_lives_one_request_then_expires(): void
    {
        $session = new Session();
        $session->start();

        $session->flash('status', 'saved');
        self::assertSame('saved', $session->get('status'));

        // Next request: flashed value still present.
        $session->start();
        self::assertSame('saved', $session->get('status'));

        // Request after that: expired.
        $session->start();
        self::assertNull($session->get('status'));
    }

    public function test_session_regenerate_flag(): void
    {
        $session = new Session();
        $session->start();
        self::assertFalse($session->regenerated);
        $session->regenerate();
        self::assertTrue($session->regenerated);
    }

    public function test_csrf_token_is_stable_and_verifiable(): void
    {
        $session = new Session();
        $session->start();
        $csrf = new Csrf($session);

        $token = $csrf->token();
        self::assertNotSame('', $token);
        self::assertSame($token, $csrf->token(), 'token should be stable within a session');
        self::assertTrue($csrf->verify($token));
        self::assertFalse($csrf->verify('wrong'));
        self::assertFalse($csrf->verify(null));
    }

    public function test_csrf_field_contains_token(): void
    {
        $session = new Session();
        $session->start();
        $csrf = new Csrf($session);

        self::assertStringContainsString($csrf->token(), $csrf->field());
        self::assertStringContainsString('name="_token"', $csrf->field());
    }

    public function test_hash_make_verify_and_needs_rehash(): void
    {
        $hash   = new Hash();
        $hashed = $hash->make('hunter2');

        self::assertNotSame('hunter2', $hashed);
        self::assertTrue($hash->verify('hunter2', $hashed));
        self::assertFalse($hash->verify('wrong', $hashed));
        self::assertFalse($hash->verify('hunter2', ''));
    }
}
