# BXCode Framework

A lightweight, developer-friendly, **shared-hosting-first** PHP framework for
modern web apps and REST APIs. No Node.js, no Redis, no background workers
required — just ordinary PHP.

> **Status: Phase 6 (Stabilized).** All six phases complete: application core,
> HTTP + routing, middleware (CSRF, security headers, CORS, rate limit, request
> logging), database + query builder + migrations, validation, sessions/auth,
> native-PHP views, cache, events/hooks/filters, storage, logging, a CLI,
> production packaging, shared-hosting tooling, a benchmark harness, and a
> passing 122-test suite. See [ARCHITECTURE.md](ARCHITECTURE.md),
> [SECURITY.md](SECURITY.md), and [docs/benchmarking.md](docs/benchmarking.md).

## Requirements

| Need | Version |
|------|---------|
| PHP | 8.3+ (tested on 8.5) |
| Extensions | `json`, `mbstring` (both standard) |
| Composer | for development only — not required in production |

## Install

```bash
composer install
cp .env.example .env
```

## Run it

```bash
composer serve        # php -S 127.0.0.1:8000 -t public
```

Then open <http://127.0.0.1:8000/api/health>.

## Your first routes

Routes live in `routes/web.php`. Every example below is exercised by the test
suite, so it works as written.

```php
use BXCode\Facades\Response;
use BXCode\Facades\Route;

Route::get('/', function () {
    return 'Welcome to BXCode';           // a string becomes an HTML response
});

Route::get('/hello/{name}', function (string $name) {
    return "Hello, {$name}!";             // {name} is injected by name
});

Route::get('/api/health', function () {
    return Response::json([                // arrays also auto-encode to JSON
        'success'   => true,
        'framework' => 'BXCode',
    ]);
});
```

**Return-value shortcuts:** a `string` → HTML, an `array` → JSON, a `Response` →
sent as-is, `null` → `204 No Content`.

## Controllers

```php
namespace App\Controllers;

use BXCode\Http\Request;
use BXCode\Http\Response;

final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::html('<h1>Welcome to BXCode</h1>');
    }
}
```

```php
Route::get('/', [App\Controllers\HomeController::class, 'index']);
```

Handler arguments are resolved by reflection: a `Request`-typed parameter gets
the request; a parameter named like a `{placeholder}` gets that path segment.

## Middleware

A middleware implements `BXCode\Http\Middleware` — do work, call `$next`, return
the `Response` (or return early to short-circuit):

```php
namespace App\Middleware;

use BXCode\Http\Middleware;
use BXCode\Http\Request;
use BXCode\Http\Response;
use Closure;

final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->withHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
```

Attach it globally, per route, or to a group:

```php
Route::middleware([App\Middleware\SecurityHeaders::class]); // every route

Route::group(['prefix' => '/api', 'middleware' => [SomeAuth::class]], function ($router) {
    $router->get('/users', [UserController::class, 'index'], 'users.index');
});

Route::url('users.index'); // "/api/users"
```

Global middleware run outside route middleware. Any middleware returning a
`Response` without calling `$next` skips the handler.

## Database

Configure connections in `config/database.php` (mysql/MariaDB and sqlite).
Query with the `DB` facade — values are always bound, never interpolated:

```php
use BXCode\Facades\DB;

$users = DB::table('users')
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

$id = DB::table('users')->insertGetId(['name' => 'Ada', 'email' => 'ada@x.io']);

$page = DB::table('users')->paginate(perPage: 15, page: 1);
// ['data' => [...], 'total', 'per_page', 'current_page', 'last_page']

DB::transaction(function () {
    DB::table('accounts')->where('id', '=', 1)->update(['balance' => 0]);
});
```

### Migrations

Migration files live in `database/migrations/` and return a `Migration`:

```php
use BXCode\Database\Connection;
use BXCode\Database\Migration;

return new class extends Migration {
    public function up(Connection $db): void   { $db->statement('CREATE TABLE ...'); }
    public function down(Connection $db): void { $db->statement('DROP TABLE ...'); }
};
```

Run them with the `Migrator` (a CLI wrapper arrives in Phase 5):

```php
(new BXCode\Database\Migrator($app->db()))->run($app->basePath('database/migrations'));
```

## Validation

```php
use BXCode\Validation\Validator;

$validated = (new Validator())->validate($request->all(), [
    'name'  => 'required|string|max:100',
    'email' => 'required|email',
    'age'   => 'integer|between:18,120',
]);
```

On failure a `ValidationException` is thrown; the error handler returns a `422`
with `{ "message": ..., "errors": { "field": [ ... ] } }`. Add a custom rule
with `->extend('name', fn ($value, $params) => ...)`.

## Views

```php
return view('home', ['title' => 'Welcome to BXCode']);
```

Templates are plain PHP in `resources/views/`. A view may extend a layout and
fill sections; always escape with `$this->e()`:

```php
<?php $this->extend('layouts.app') ?>
<?php $this->section('content') ?>
    <h1><?= $this->e($title) ?></h1>
    <?php $this->insert('partials.footer') ?>
<?php $this->endSection() ?>
```

The layout outputs sections with `<?= $this->yieldSection('content') ?>`.

## Sessions, CSRF, hashing

```php
session()->set('key', 'value');
session()->flash('status', 'Saved!');   // available next request only

echo csrf_field();                        // hidden _token input for forms
```

Protect write routes with the CSRF middleware (checks `_token` or the
`X-CSRF-Token` header; safe methods pass through):

```php
use BXCode\Middleware\CsrfMiddleware;

Route::post('/profile', [ProfileController::class, 'update'], null, [CsrfMiddleware::class]);
```

```php
use BXCode\Security\Hash;

$hash = (new Hash())->make($password);
(new Hash())->verify($password, $hash);   // bool
```

## Authentication

Implement `BXCode\Auth\UserProvider` (or use `DatabaseUserProvider`) and guard
with `SessionGuard`:

```php
use BXCode\Auth\DatabaseUserProvider;
use BXCode\Auth\SessionGuard;
use BXCode\Security\Hash;

$guard = new SessionGuard(
    new DatabaseUserProvider($app->db(), new Hash()),
    $app->session(),
);

if ($guard->attempt(['email' => $email, 'password' => $password])) {
    // logged in; session id regenerated automatically
}

$guard->check();   // bool
$guard->user();    // ?Authenticatable
$guard->logout();
```

## Errors

Throw `BXCode\Http\HttpException` for clean HTTP errors; it is rendered as JSON
or HTML by content negotiation, never leaking internals:

```php
use BXCode\Http\HttpException;

throw HttpException::forbidden('You cannot do that.');
```

Genuine (unexpected) exceptions are logged and return a production-safe `500`
unless `APP_DEBUG=true`.

## Logging

```php
$app->logger()->info('User updated', ['id' => 7, 'password' => 'hunter2']);
// the password value is written as [redacted]
```

Levels: `debug`, `info`, `warning`, `error` (filtered by `LOG_LEVEL`). Logs go
to `storage/logs/app.log`.

## Cache

```php
use BXCode\Facades\Cache;

Cache::put('key', $value, 3600);          // seconds; null = forever
$value = Cache::get('key', 'default');
Cache::forget('key');

$users = Cache::remember('users.active', 600, function () {
    return DB::table('users')->where('status', '=', 'active')->get();
});
```

Filesystem-backed by default (`storage/cache`). A key prefix namespaces entries.

## Events, hooks, and filters

```php
use BXCode\Facades\Event;

// Events / action hooks — listeners run in priority order (lower first):
Event::listen('user.registered', fn ($user) => /* ... */ null, priority: 10);
Event::dispatch('user.registered', $user);

// Filters — thread a value through a chain:
Event::addFilter('post.title', fn (string $t) => trim($t));
$title = Event::applyFilter('post.title', $rawTitle);
```

Listener exceptions propagate (never silently swallowed). This is the extension
point a future plugin system builds on.

## Storage

```php
use BXCode\Facades\Storage;

Storage::put('reports/april.csv', $csv);
$data = Storage::get('reports/april.csv');
Storage::delete('reports/april.csv');
```

All paths are relative to the disk root (`storage/app`); `../` traversal and
null bytes are rejected.

## File uploads

```php
use BXCode\Http\UploadedFile;

$file   = UploadedFile::fromArray($_FILES['avatar']);
$errors = $file->validate(maxBytes: 2_000_000, allowedExtensions: ['jpg', 'png']);

if ($errors === []) {
    $path = $file->store(Storage::disk(), 'avatars'); // random, safe filename
}
```

## Config and environment

```php
config('app.name');          // reads config/app.php
config('app.debug', false);  // second arg is the default

env('APP_ENV', 'production');
env('APP_DEBUG', false);
```

Config files are plain PHP in `config/`, each returning an array. `.env` is a
simple `KEY=value` file (no external dependency); `true`/`false`/`null` are cast.

## Project layout

```text
config/app.php        application config
public/index.php      the single front controller
public/.htaccess      Apache / LiteSpeed rewrite to the front controller
routes/web.php        your routes
src/                  the BXCode\ framework (Application, Http, Routing, Facades)
tests/                PHPUnit Unit + Integration suites
```

## Tests

```bash
composer test         # or: ./vendor/bin/phpunit
```

## CLI

The `bxcode` console is optional (never required in production):

```bash
php bxcode                              # list commands
php bxcode make:controller HomeController
php bxcode make:middleware EnsureAdmin
php bxcode make:migration create_posts_table
php bxcode migrate                      # and migrate:rollback
php bxcode route:list
php bxcode cache:clear
php bxcode serve --host=127.0.0.1 --port=8000
php bxcode test
```

## Shared hosting

Point the document root at `public/` (preferred), or use the project-root
`.htaccess` fallback. Where `mod_rewrite` is unavailable, `/index.php/<path>`
works because the router reads `REQUEST_URI`.

Build a ready-to-upload ZIP (bundles `vendor/`, so the host needs no Composer):

```bash
composer install --no-dev --optimize-autoloader
php bin/package.php        # writes dist/bxcode.zip
composer install
```

After uploading, open `https://your-site/check.php` (or run
`php public/check.php`) to verify PHP version, extensions, and storage
permissions, then delete it. Full guide: [docs/shared-hosting.md](docs/shared-hosting.md).

## License

MIT.
