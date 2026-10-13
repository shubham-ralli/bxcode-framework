<?php

declare(strict_types=1);

/*
 | Rate-limit settings for the RateLimit middleware. Edit directly — no .env file.
 */

return [
    // Max requests allowed per client IP + path within the decay window.
    'max_attempts' => 60,

    // Window length in seconds.
    'decay_seconds' => 60,
];
