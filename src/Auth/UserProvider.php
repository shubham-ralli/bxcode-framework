<?php

declare(strict_types=1);

namespace BXCode\Auth;

/**
 * Source of users for a guard. Implementations decide where users live
 * (database, array, API). The guard depends only on this interface.
 */
interface UserProvider
{
    public function retrieveById(int|string $id): ?Authenticatable;

    /**
     * Find a user by non-secret credentials (e.g. email), without checking the
     * password. Returns null when no such user exists.
     *
     * @param array<string,mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Verify a user's password against the given credentials.
     *
     * @param array<string,mixed> $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
