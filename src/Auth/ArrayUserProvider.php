<?php

declare(strict_types=1);

namespace BXCode\Auth;

use BXCode\Security\Hash;

/**
 * An in-memory user provider, handy for tests and tiny apps. Users are rows of
 * attributes including an `id` and a hashed `password`.
 */
final class ArrayUserProvider implements UserProvider
{
    /**
     * @param list<array<string,mixed>> $users
     */
    public function __construct(
        private readonly array $users,
        private readonly Hash $hash = new Hash(),
    ) {
    }

    public function retrieveById(int|string $id): ?Authenticatable
    {
        foreach ($this->users as $user) {
            if (($user['id'] ?? null) == $id) { // loose: ids may be int in array, string from session
                return new GenericUser($user);
            }
        }

        return null;
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        foreach ($this->users as $user) {
            $match = true;
            foreach ($credentials as $key => $value) {
                if ($key === 'password') {
                    continue;
                }
                if (($user[$key] ?? null) !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return new GenericUser($user);
            }
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        return is_string($plain) && $this->hash->verify($plain, $user->getAuthPassword());
    }
}
