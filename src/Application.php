<?php

declare(strict_types=1);

namespace BXCode;

use BXCode\Cache\FileStore;
use BXCode\Cache\Store;
use BXCode\Database\Connection;
use BXCode\Events\Dispatcher;
use BXCode\Http\HttpException;
use BXCode\Http\Request;
use BXCode\Http\Response;
use BXCode\Logging\Logger;
use BXCode\Routing\Router;
use BXCode\Security\Session;
use BXCode\Storage\Filesystem;
use BXCode\Storage\LocalFilesystem;
use BXCode\Validation\ValidationException;
use BXCode\View\Factory as ViewFactory;
use Throwable;

/**
 * The framework kernel: boots once, owns the Router and configuration, and runs
 * the request lifecycle (capture -> dispatch -> send). Kept deliberately small —
 * a DI container, middleware pipeline and views arrive in later phases.
 */
final class Application
{
    private static ?self $instance = null;

    private Router $router;

    private ?Connection $connection = null;

    private ?Session $session = null;

    private ?ViewFactory $view = null;

    private ?Logger $logger = null;

    private ?Store $cache = null;

    private ?Dispatcher $events = null;

    private ?Filesystem $storage = null;

    /** @var array<string,mixed> Merged config, keyed by file then dot-path. */
    private array $config = [];

    private bool $debug = false;

    private function __construct(private readonly string $basePath)
    {
        $this->router = new Router();
    }

    /**
     * Boot the application from a project root. Loads .env, then every PHP file
     * in config/ (each returns an array, keyed under its filename).
     */
    public static function boot(string $basePath): self
    {
        $app = new self(rtrim($basePath, '/'));
        self::$instance = $app;

        $app->loadEnv($app->basePath . '/.env');
        $app->loadConfig($app->basePath . '/config');
        $app->debug = (bool) $app->config('app.debug', false);

        return $app;
    }

    /** The booted instance. Facades resolve through this. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('BXCode application has not been booted.');
        }

        return self::$instance;
    }

    /** Only tests should need this (to reset global state between cases). */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    /**
     * The default database connection, built lazily from the "database" config
     * on first use. No connection is opened until a query actually runs.
     */
    public function db(): Connection
    {
        if ($this->connection === null) {
            $default = (string) $this->config('database.default', 'mysql');
            /** @var array<string,mixed> $config */
            $config = $this->config("database.connections.{$default}", []);
            $this->connection = new Connection($config);

            if ((bool) $this->config('database.log_queries', $this->debug)) {
                $this->connection->enableQueryLog();
            }
        }

        return $this->connection;
    }

    /** Override the default connection (tests inject an in-memory sqlite here). */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /** The session, started lazily with secure cookie defaults. */
    public function session(): Session
    {
        if ($this->session === null) {
            $this->session = new Session();
            $this->session->start();
        }

        return $this->session;
    }

    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /** The view factory, resolving templates from the configured directory. */
    public function view(): ViewFactory
    {
        if ($this->view === null) {
            $path = (string) ($this->config('view.path') ?? '') ?: $this->basePath('resources/views');
            $this->view = new ViewFactory($path);
        }

        return $this->view;
    }

    /** The application logger, writing to the configured log file. */
    public function logger(): Logger
    {
        if ($this->logger === null) {
            $file  = (string) ($this->config('logging.path') ?? '') ?: $this->basePath('storage/logs/app.log');
            $level = (string) ($this->config('logging.level') ?? '') ?: 'debug';
            $this->logger = new Logger($file, $level);
        }

        return $this->logger;
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    /** The default cache store (filesystem), built lazily from "cache" config. */
    public function cache(): Store
    {
        if ($this->cache === null) {
            $path   = (string) ($this->config('cache.path') ?? '') ?: $this->basePath('storage/cache');
            $prefix = (string) $this->config('cache.prefix', '');
            $this->cache = new FileStore($path, $prefix);
        }

        return $this->cache;
    }

    public function setCache(Store $cache): void
    {
        $this->cache = $cache;
    }

    /** The event/hook/filter dispatcher (a single shared instance). */
    public function events(): Dispatcher
    {
        return $this->events ??= new Dispatcher();
    }

    /** The default storage disk (local), built lazily from "storage" config. */
    public function storage(): Filesystem
    {
        if ($this->storage === null) {
            $root = (string) ($this->config('storage.root') ?? '') ?: $this->basePath('storage/app');
            $this->storage = new LocalFilesystem($root);
        }

        return $this->storage;
    }

    public function setStorage(Filesystem $storage): void
    {
        $this->storage = $storage;
    }

    public function basePath(string $append = ''): string
    {
        return $this->basePath . ($append !== '' ? '/' . ltrim($append, '/') : '');
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /** Dispatch a request to a Response. Pure and testable — no output here. */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    /** Capture the live request, dispatch it, and send the response. */
    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    /**
     * Read a config value by "file.key.subkey" dot path.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value    = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** Read an environment value with a typed-ish default fallback. */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    /** Minimal .env loader. No external dependency; KEY=value, # comments, quotes. */
    private function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip one layer of matching quotes.
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '' && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }

    private function loadConfig(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                $this->config[$name] = $data;
            }
        }
    }

    private function renderException(Throwable $e, Request $request): Response
    {
        $wantsJson = $request->wantsJson();

        // Validation failures: predictable structured 422.
        if ($e instanceof ValidationException) {
            return Response::json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        }

        // Explicit HTTP errors: their status, message only (never internals).
        if ($e instanceof HttpException) {
            $response = $wantsJson
                ? Response::json(['message' => $e->getMessage()], $e->status)
                : Response::html(
                    '<!doctype html><title>' . $e->status . '</title><h1>' . $e->status . '</h1>'
                    . '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>',
                    $e->status,
                );
            foreach ($e->headers as $name => $value) {
                $response->withHeader($name, $value);
            }

            return $response;
        }

        // Genuine failures: log them, then respond.
        $this->logException($e);

        if (!$this->debug) {
            return $wantsJson
                ? Response::json(['message' => 'Server Error'], 500)
                : Response::html(
                    '<!doctype html><title>500 Server Error</title><h1>500 Server Error</h1>'
                    . '<p>The application encountered an error.</p>',
                    500,
                );
        }

        if ($wantsJson) {
            return Response::json([
                'message'   => $e->getMessage(),
                'exception' => $e::class,
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }

        $body = '<!doctype html><title>Unhandled Exception</title>'
            . '<h1>' . htmlspecialchars($e::class, ENT_QUOTES) . '</h1>'
            . '<p><strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</strong></p>'
            . '<p>' . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES) . '</p>'
            . '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';

        return Response::html($body, 500);
    }

    private function logException(Throwable $e): void
    {
        try {
            $this->logger()->error($e->getMessage(), [
                'exception' => $e::class,
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
        } catch (Throwable) {
            // Never let logging failures mask the original error.
        }
    }
}
