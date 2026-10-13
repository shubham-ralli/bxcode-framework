<?php

declare(strict_types=1);

return [
    // Max requests allowed per client IP + path within the decay window.
    'max_attempts' => (int) env('RATELIMIT_MAX', 60),

    // Window length in seconds.
    'decay_seconds' => (int) env('RATELIMIT_DECAY', 60),
];
