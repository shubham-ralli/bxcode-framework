<?php

declare(strict_types=1);

namespace BXCode\Security;

/**
 * Session wrapper over PHP's native session with secure cookie defaults and
 * flash data. In CLI/test contexts it falls back to an internal array store, so
 * session-backed code stays unit-testable without a live session.
 */
final class Session
{
    /** @var array<string,mixed> */
    private array $store = [];

    private bool $started = false;

    private bool $native = false;

    public bool $regenerated = false;

    /** Start the session, applying secure cookie params and ageing flash data. */
    public function start(): void
    {
        if ($this->started) {
            $this->ageFlash();

            return;
        }

        $this->native = PHP_SAPI !== 'cli'
            && session_status() !== PHP_SESSION_ACTIVE
            && !headers_sent();

        if ($this->native) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
            ]);
            session_start();
            $this->store = &$_SESSION;
        }

        $this->started = true;
        $this->ageFlash();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->store;
    }

    /** Clear everything in the session. */
    public function flush(): void
    {
        $this->store = [];
    }

    /** Store a value available on the next request only. */
    public function flash(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
        $new = $this->store['_flash_new'] ?? [];
        $new[] = $key;
        $this->store['_flash_new'] = array_values(array_unique($new));
        // Don't let this key be aged out by the batch it was just added to.
        $this->store['_flash_old'] = array_values(array_diff($this->store['_flash_old'] ?? [], [$key]));
    }

    /** Regenerate the session id (call after login to prevent fixation). */
    public function regenerate(): void
    {
        $this->regenerated = true;
        if ($this->native) {
            session_regenerate_id(true);
        }
    }

    /** Move last request's flash to "old" and expire the batch before it. */
    private function ageFlash(): void
    {
        foreach ($this->store['_flash_old'] ?? [] as $key) {
            unset($this->store[$key]);
        }
        $this->store['_flash_old'] = $this->store['_flash_new'] ?? [];
        $this->store['_flash_new'] = [];
    }
}
