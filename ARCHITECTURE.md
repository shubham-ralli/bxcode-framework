# Architecture

BXCode is a small, explicit framework. There is no magic container resolution,
no annotations, and no hidden global state beyond one booted `Application`
instance that facades resolve through.

## Request lifecycle

```
public/index.php
   │  require vendor/autoload.php
   │  $app = Application::boot($root)      // load .env, load config/*.php
   │  require routes/web.php               // register routes
   │  $app->run()
   ▼
Request::capture()                         // from PHP superglobals
   ▼
Application::handle(Request)               // try/catch → central error handler
   ▼
Router::dispatch(Request)
   │  match path + method (404 / 405 / HEAD→GET)
   ▼
Pipeline::run(globalMiddleware + routeMiddleware, destination)
   │  middleware run outside-in; any may short-circuit
   ▼
destination: resolve handler args by reflection, invoke, normalise return
   │  string → HTML, array → JSON, Response → as-is, Stringable → HTML, null → 204
   ▼
Response::send()                           // status + headers + body
```

Exceptions are caught in `handle()`: `ValidationException` → 422 JSON,
`HttpException` → its status (JSON or HTML by negotiation), anything else → a
production-safe 500 (full detail only when `APP_DEBUG=true`), logged either way.

## Modules

| Namespace | Responsibility |
|-----------|----------------|
| `BXCode` (`Application`) | Boot, config, `.env`, lifecycle, lazy service accessors, central error handling. |
| `Http` | `Request`, `Response`, `Middleware` contract, `HttpException`, `UploadedFile`. |
| `Routing` | `Router` (verbs, params, groups, named routes, middleware), `Pipeline`. |
| `Middleware` | CSRF, security headers, CORS, rate limit, request logging. |
| `Database` | `Connection` (PDO), `QueryBuilder`, `Migration`, `Migrator`. |
| `Validation` | `Validator`, `ValidationException`. |
| `Security` | `Session`, `Hash`, `Csrf`, `RateLimiter`. |
| `Auth` | `Authenticatable`/`UserProvider` interfaces, `SessionGuard`, providers. |
| `View` | `Factory`, `TemplateRenderer`, `View` (native PHP templates). |
| `Cache` | `Store` contract, `FileStore`. |
| `Events` | `Dispatcher` (events/hooks + filters). |
| `Storage` | `Filesystem` contract, `LocalFilesystem`. |
| `Logging` | `Logger`. |
| `Console` | `Kernel`, `Command`, `Output`, and the built-in commands. |
| `Support` | `EnvironmentCheck`. |
| `Facades` | Static entry points (`Route`, `Response`, `DB`, `Cache`, `Event`, `Storage`). |

## Design principles

- **Lazy services.** Connections, sessions, views, cache, logger, and storage
  are built on first use. A plain-text route opens no database connection.
- **Interfaces at the seams.** `Cache\Store`, `Storage\Filesystem`, and
  `Auth\UserProvider` let backends be swapped (Redis, S3, a user model) without
  touching callers — the extension path, no core changes.
- **Testability.** `handle()` is pure (no output); `send()` is the only method
  with global side effects. Services have `set*()` injectors for tests.
- **No framework → CMS coupling.** Events/hooks/filters are the extension point
  a future CMS plugin system builds on; the core knows nothing about it.

## What is deliberately absent

No ORM (query builder only), no annotation routing, no DI auto-wiring, no
compiled template language, no route caching (closures are not serializable).
These are intentional — see each phase's notes in the `CHANGELOG`.
