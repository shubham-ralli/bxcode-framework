---
name: bxcode-feature
description: Scaffold a complete feature in the BXCode framework the conventional way — route, controller, request validation, view (or JSON), optional middleware, and a test. Use when adding a new endpoint, page, or feature to a BXCode app, or when the user says "add a route/controller/feature" in this repo.
---

# Build a BXCode feature end to end

Follow these steps in order. Verify each API against `src/` if unsure.

## 1. Generate the controller
```bash
php bxcode make:controller <Name>Controller   # -> app/Controllers/<Name>Controller.php
```
Make the method typed and return a `Response` (or a `view()`/array):
```php
public function store(Request $request): Response
{
    $data = (new \BXCode\Validation\Validator())->validate($request->all(), [
        'title' => 'required|string|max:200',
        'email' => 'required|email',
    ]);
    // ... use $data (already validated) ...
    return Response::json(['ok' => true], 201);
}
```

## 2. Register the route
In `routes/web.php`:
```php
use BXCode\Facades\Route;

Route::post('/items', [App\Controllers\ItemController::class, 'store'], 'items.store',
    [\BXCode\Middleware\CsrfMiddleware::class]);        // CSRF on writes
Route::get('/items/{id}', [App\Controllers\ItemController::class, 'show'], 'items.show');
```
- Return types auto-map: string→HTML, array→JSON, `Response`→as-is, `view()`→HTML.
- Group shared prefix/middleware with `Route::group([...], fn($r)=>...)`.

## 3. Validation
Use `Validator` (rules: required, string, integer, numeric, boolean, email, url,
date, array, max, min, between, in; custom via `->extend()`). A failure throws
`ValidationException` → the handler returns a 422 with `{message, errors}`.

## 4. Output
- JSON API → `Response::json($data, $status)`.
- HTML page → `return view('items.show', ['item' => $item]);` with a template in
  `resources/views/items/show.php`. Escape everything with `$this->e()`.

## 5. Security (don't skip)
- CSRF middleware on every state-changing route.
- Add `SecurityHeaders`/`Cors`/`RateLimit` where appropriate.
- Never echo request data unescaped; never build SQL by concatenation (use `DB`).

## 6. Verify
```bash
php bxcode route:list          # confirm the route registered
php bxcode serve               # smoke test in the browser
```
Add a test in `tests/` dispatching the route and asserting status/body, then:
```bash
./vendor/bin/phpunit
```
Report the real route:list output and the phpunit summary. Don't claim done
until both are shown.
