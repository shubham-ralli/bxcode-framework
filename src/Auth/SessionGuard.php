<?php

declare(strict_types=1);

namespace BXCode\Auth;

use BXCode\Security\Session;

/**
 * Session-backed authentication guard. Stores the authenticated user's id in the
 * session and regenerates the session id on login to prevent fixation.
 */
final class SessionGuard
{
    private const SESSION_KEY = '_auth_id';

    private ?Authenticatable $user = null;

    private bool $resolved = false;

    public function __construct(
        private readonly UserProvider $provider,
        private readonly Session $session,
    ) {
    }

    /**
     * Attempt to authenticate with the given credentials. On success the user is
     * logged in and true is returned.
     *
     * @param array<string,mixed> $credentials
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user !== null && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user);

            return true;
        }

        return false;
    }

    /** Log a user in and regenerate the session id. */
    public function login(Authenticatable $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->getAuthId());
        $this->user     = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
        $this->user     = null;
        $this->resolved = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /** The authenticated user, loaded lazily from the session id, or null. */
    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->session->get(self::SESSION_KEY);

        if ($id !== null) {
            $this->user = $this->provider->retrieveById($id);
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthId();
    }
}
