<?php

declare(strict_types=1);

use BXCode\Facades\Response;
use BXCode\Facades\Route;

/*
 | Application routes. The $router is the booted Application's Router; the
 | Route facade points at the same instance, so use whichever reads better.
 */

Route::get('/', function () {
    return 'Welcome to BXCode';
});

Route::get('/hello/{name}', function (string $name) {
    return 'Hello, ' . htmlspecialchars($name, ENT_QUOTES) . '!';
});

// Health check — the Phase 1 deliverable. Cheap, dependency-free, JSON.
Route::get('/api/health', function () {
    return Response::json([
        'success'   => true,
        'framework' => 'BXCode',
        'status'    => 'ok',
        'time'      => gmdate('c'),
    ]);
});
