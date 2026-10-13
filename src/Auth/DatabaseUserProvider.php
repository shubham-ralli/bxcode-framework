<?php

declare(strict_types=1);

namespace BXCode\Auth;

use BXCode\Database\Connection;
use BXCode\Security\Hash;

/**
 * Loads users from a database table. Credentials other than the password are
 * matched with equality; the password column is verified via Hash, never
 * queried directly.
 */
final class DatabaseUserProvider implements UserProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Hash $hash,
        private readonly string $table = 'users',
        private readonly string $passwordColumn = 'password',
    ) {
    }

    public function retrieveById(int|string $id): ?Authenticatable
    {
        $row = $this->connection->table($this->table)->where('id', '=', $id)->first();

        return $row === null ? null : new GenericUser($row);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $query    = $this->connection->table($this->table);
        $hasWhere = false;

        foreach ($credentials as $key => $value) {
            if ($key === $this->passwordColumn || !is_string($key)) {
                continue;
            }
            $query->where($key, '=', $value);
            $hasWhere = true;
        }

        if (!$hasWhere) {
            return null;
        }

        $row = $query->first();

        return $row === null ? null : new GenericUser($row);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials[$this->passwordColumn] ?? null;

        return is_string($plain) && $this->hash->verify($plain, $user->getAuthPassword());
    }
}
