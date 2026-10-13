<?php

declare(strict_types=1);

namespace BXCode\Auth;

/**
 * A minimal Authenticatable backed by an attribute array. Enough to authenticate
 * with before an application defines its own user model.
 */
final class GenericUser implements Authenticatable
{
    /** @param array<string,mixed> $attributes */
    public function __construct(private readonly array $attributes)
    {
    }

    public function getAuthId(): int|string
    {
        /** @var int|string $id */
        $id = $this->attributes['id'] ?? '';

        return $id;
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
