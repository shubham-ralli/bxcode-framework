<?php

declare(strict_types=1);

use BXCode\Application;

if (!function_exists('app')) {
    /** The booted application instance. */
    function app(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('config')) {
    /** Read a config value by dot path, e.g. config('app.name'). */
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config($key, $default);
    }
}

if (!function_exists('env')) {
    /** Read an environment value, e.g. env('APP_ENV', 'production'). */
    function env(string $key, mixed $default = null): mixed
    {
        return Application::env($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape a value for safe HTML output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view')) {
    /**
     * Render a view. Returns a Stringable View, so a route may `return view(...)`.
     *
     * @param array<string,mixed> $data
     */
    function view(string $name, array $data = []): BXCode\View\View
    {
        return Application::getInstance()->view()->make($name, $data);
    }
}

if (!function_exists('session')) {
    /** The session instance (session()->get('key'), session()->set(...)). */
    function session(): BXCode\Security\Session
    {
        return Application::getInstance()->session();
    }
}

if (!function_exists('csrf_token')) {
    /** The current CSRF token. */
    function csrf_token(): string
    {
        return (new BXCode\Security\Csrf(Application::getInstance()->session()))->token();
    }
}

if (!function_exists('csrf_field')) {
    /** A hidden CSRF input for HTML forms. */
    function csrf_field(): string
    {
        return (new BXCode\Security\Csrf(Application::getInstance()->session()))->field();
    }
}

if (!function_exists('cache')) {
    /** The default cache store (cache()->get(...), cache()->put(...)). */
    function cache(): BXCode\Cache\Store
    {
        return Application::getInstance()->cache();
    }
}
