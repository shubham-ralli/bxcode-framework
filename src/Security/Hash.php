<?php

declare(strict_types=1);

namespace BXCode\Security;

/**
 * Password hashing. A thin, safe wrapper over PHP's password_* functions —
 * algorithm-agnostic, with verification and rehash detection.
 */
final class Hash
{
    public function __construct(
        private readonly string $algo = PASSWORD_DEFAULT,
        /** @var array<string,int> */
        private readonly array $options = [],
    ) {
    }

    public function make(string $password): string
    {
        return password_hash($password, $this->algo, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo, $this->options);
    }
}
