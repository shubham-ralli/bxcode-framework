<?php

declare(strict_types=1);

namespace BXCode\Security;

/**
 * CSRF token management, backed by the session. One token per session; compared
 * in constant time. Pair with CsrfMiddleware to enforce it on state-changing
 * requests.
 */
final class Csrf
{
    public const SESSION_KEY = '_token';

    public function __construct(private readonly Session $session)
    {
    }

    /** The current token, generating and storing one on first use. */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /** Constant-time comparison against the stored token. */
    public function verify(?string $token): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
    }

    /** A hidden input carrying the token, for HTML forms. */
    public function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }
}
