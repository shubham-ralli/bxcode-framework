<?php

declare(strict_types=1);

return [
    // Use ['*'] to allow any origin, or list exact origins e.g.
    // ['https://app.example.com'].
    'allowed_origins' => ['*'],

    'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',

    'allowed_headers' => 'Content-Type, X-CSRF-Token, Authorization',
];
