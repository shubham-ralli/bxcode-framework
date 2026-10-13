<?php

declare(strict_types=1);

namespace BXCode\Auth;

/**
 * A user that can be authenticated. Applications implement this on their user
 * model; the framework never assumes how users are stored.
 */
interface Authenticatable
{
    /** The unique identifier stored in the session. */
    public function getAuthId(): int|string;

    /** The hashed password, used to verify credentials. */
    public function getAuthPassword(): string;
}
