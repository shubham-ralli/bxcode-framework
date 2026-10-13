<?php

declare(strict_types=1);

return [
    // Cache directory. Defaults to storage/cache via Application when unset.
    'path' => env('CACHE_PATH', null),

    // Key prefix for namespacing (useful when apps share a directory).
    'prefix' => env('CACHE_PREFIX', ''),
];
