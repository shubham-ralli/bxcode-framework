# Changelog

All notable changes to BXCode are documented here.

## [Unreleased]

### Phase 6 — Stabilization

- **Composer-free, .env-free operation (CodeIgniter-3 style)**: added
  `bootstrap/autoload.php`, a native PSR-4 autoloader. `public/index.php`,
  `bxcode`, and `public/check.php` use Composer's autoloader when present and
  fall back to it otherwise, so the app runs with no `vendor/`. `bootstrap/` is
  bundled in the production package.
- **Removed the `.env` file entirely.** Dropped the `.env` loader and
  `.env.example`; all settings are now literal values in `config/*.php`, edited
  directly. Added `Application::setConfig($key, $value)` for runtime overrides;
  `isDebug()` reads `config('app.debug')` live. `env()` remains only as an
  optional reader of a real server environment variable (reads no file).

- **Closed spec middleware gaps**: `Middleware\SecurityHeaders`,
  `Middleware\Cors` (config-driven, preflight), `Middleware\RateLimit` (429 +
  Retry-After, X-RateLimit headers) backed by `Security\RateLimiter`, and
  `Middleware\RequestLogging`. `config/cors.php`, `config/ratelimit.php`.
- **Security hardening**: view names are charset-validated (defence in depth);
  `Request::ip()` added (REMOTE_ADDR only, proxy headers untrusted).
- **Benchmark harness** (`bin/benchmark.php`): per-scenario mean/p50/p95, query
  count, peak memory for the in-process request lifecycle. Results and
  methodology in `docs/benchmarking.md` (no cross-framework comparison claimed).
- **Docs**: `SECURITY.md` (protections + app responsibilities), `ARCHITECTURE.md`
  (lifecycle, modules, principles), `docs/benchmarking.md`.
- `Application::setLogger()` for testability.
- Full suite: 122 tests, 283 assertions, all passing.

### Phase 5 — Tools and Deployment

- **CLI** (`bxcode` + `Console\Kernel`/`Command`/`Output`): dependency-free
  console with argv parsing (positional args + `--options`), command listing,
  and error handling. Commands: `make:controller`, `make:middleware`,
  `make:migration`, `migrate`, `migrate:rollback`, `route:list`, `cache:clear`,
  `serve`, `test`. Never required in production.
- **Router** gains `all()` to expose registered routes for `route:list`.
- **Production packaging** (`bin/package.php`): builds `dist/bxcode.zip` with
  `vendor/` bundled (no Composer needed on the host), excluding dev files;
  warns when dev dependencies are present.
- **Shared-hosting config**: project-root `.htaccess` fallback (forwards to
  `public/`, blocks private paths); `public/.htaccess` already present.
- **Setup checker** (`Support\EnvironmentCheck` + `public/check.php`): verifies
  PHP version, required/recommended extensions, and storage writability, in the
  browser or CLI.
- **Docs**: `docs/shared-hosting.md` (Apache/LiteSpeed, both layouts, packaging,
  permissions, migrations, honest limitations).
- Test suite now 115 tests, 262 assertions, all passing.

### Phase 4 — Cache and Extensions

- **Cache** (`Cache\Store` interface + `Cache\FileStore`): get/put/has/forget/
  flush/forever/remember, TTL expiry, atomic writes (temp+rename), key-prefix
  namespacing, correct handling of cached `null`. `Cache` facade, `cache()`
  helper, `Application::cache()`, `config/cache.php`.
- **Events / hooks / filters** (`Events\Dispatcher`): `listen`/`dispatch`
  (priority then registration order, returns listener values),
  `addFilter`/`applyFilter` (value threaded through chain), `hasListeners`;
  listener exceptions propagate (never swallowed). `Event` facade,
  `Application::events()`. Extension point for a future CMS plugin system.
- **Storage** (`Storage\Filesystem` interface + `Storage\LocalFilesystem`):
  get/put/append/exists/delete/size/makeDirectory/files, with path
  normalisation + traversal and null-byte rejection. `Storage` facade,
  `Application::storage()`, `config/storage.php`.
- **File uploads** (`Http\UploadedFile`): size + extension validation, store
  with a server-generated random name (client filename never used in the path).
- Test suite now 104 tests, 234 assertions, all passing.

### Phase 3 — Security and Developer Experience

- **Validation** (`Validation\Validator`): pipe-string rules (required, string,
  integer, numeric, boolean, email, url, date, array, max, min, between, in) +
  custom rules via `extend()`; optional-field skipping; `ValidationException`
  with structured per-field messages (422).
- **Sessions** (`Security\Session`): secure cookie defaults, get/set/has/forget/
  flush, one-request flash, id regeneration; array-store fallback under CLI/tests.
- **CSRF** (`Security\Csrf` + `Middleware\CsrfMiddleware`): per-session token,
  constant-time verify, `_token`/`X-CSRF-Token` enforcement on writes (419),
  `csrf_token()`/`csrf_field()` helpers.
- **Password hashing** (`Security\Hash`): make/verify/needsRehash wrapper.
- **Auth** (`Auth\*`): `Authenticatable` + `UserProvider` interfaces,
  `SessionGuard` (attempt/login/logout/check/user/id, regenerates on login),
  `ArrayUserProvider` and `DatabaseUserProvider`, `GenericUser`.
- **Views** (`View\*`): native PHP templates with layouts (`extend`), sections,
  partials (`insert`), escaping (`e()`); `view()` returns a Stringable View.
- **Consistent errors**: `Http\HttpException`; central handler negotiates JSON
  vs HTML, renders Validation (422) and HTTP exceptions cleanly, stays
  production-safe for the rest, logs genuine failures.
- **Logging** (`Logging\Logger`): level filtering, per-request id, recursive
  redaction of secret-looking context keys, file output.
- Helpers `e()`, `view()`, `session()`, `csrf_token()`, `csrf_field()`;
  `Application::session()/view()/logger()`; `config/logging.php`; example views.
- Test suite now 77 tests, 177 assertions, all passing.

### Phase 2 — HTTP and Database

- **Middleware pipeline**: `Http\Middleware` interface, `Routing\Pipeline`
  runner. Middleware run outside-in; any may short-circuit by returning a
  Response. Entries may be instances, class-strings, or closures.
- **Router** extended: global middleware (`middleware()`), per-route middleware,
  `group()` with shared prefix + middleware (nests), named-route `url()`
  generation. Phase 1 routing behaviour unchanged.
- **Database\Connection**: lazy PDO (mysql/MariaDB + sqlite), prepared
  statements only, `select`/`selectOne`/`affectingStatement`/`statement`,
  nestable `transaction()`, in-memory query log with slow-query filter,
  password-safe connection errors.
- **Database\QueryBuilder**: `where`/`orWhere`/`whereIn`/`orderBy`/`limit`/
  `offset`/`get`/`first`/`value`/`count`/`exists`/`insert`/`insertGetId`/
  `update`/`delete`/`paginate`. Values always bound; identifiers validated.
- **Database\Migrator** + `Migration` base class: batch-tracked run/rollback
  over PHP migration files; example `create_users_table` migration.
- **DB facade**, `Application::db()` lazy default connection, `config/database.php`.
- Test suite now 48 tests, 101 assertions, all passing.

### Phase 1 — Foundation

- **Application** kernel: `boot()`, request lifecycle (`handle()`/`run()`),
  `.env` loader (no external dependency), config loader, dot-path `config()`,
  `env()` casting, production-safe vs. debug exception rendering.
- **Http\Request**: captures from superglobals, query/body/header/param
  accessors, `all()`, immutable `withParams()`.
- **Http\Response**: `make`/`html`/`text`/`json`/`redirect` factories,
  case-insensitive headers, `send()`.
- **Routing\Router**: GET/POST/PUT/PATCH/DELETE/HEAD, `{param}` matching,
  closure and `[Controller::class, 'method']` dispatch with reflection-based
  argument resolution, return-value normalisation, 404/405, HEAD→GET fallback,
  custom not-found handler.
- **Facades**: `Route` and `Response` for clean static syntax.
- **Helpers**: `app()`, `config()`, `env()`.
- Health-check route `GET /api/health`, Apache/LiteSpeed `.htaccess`.
- 23 PHPUnit tests (Unit + Integration), all passing.
