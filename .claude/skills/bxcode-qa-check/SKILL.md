---
name: bxcode-qa-check
description: Run the full QA gate for the BXCode framework before shipping — execute the test suite, run a security checklist against the framework's contract, benchmark if relevant, and verify the composer-free/.env-free bootstrap. Use before merging, before a release, or when the user asks to "QA", "test everything", or "check before shipping".
---

# BXCode pre-ship QA gate

Run every step and report real output. Do not declare pass without evidence.

## 1. Full test suite
```bash
./vendor/bin/phpunit
```
Must end with `OK (N tests, M assertions)`. If anything fails, report the test,
message, and fix — never hide it. `phpunit.xml` has `failOnWarning`/`failOnRisky`
on, so warnings are failures.

## 2. Bug fixes need a regression test
For any fix in this session, confirm a test exists that fails without it and
passes with it. No test → add one.

## 3. Security checklist (against SECURITY.md)
Grep + read the changed code and confirm:
- [ ] No raw SQL with concatenated request data; values bound; identifiers via the builder.
- [ ] Views escape output (`$this->e()` / `e()`); no unescaped `<?= $requestVar ?>`.
- [ ] State-changing routes carry `CsrfMiddleware`.
- [ ] Login goes through `SessionGuard` (session id regenerated).
- [ ] File reads/includes on request-derived paths go through `LocalFilesystem`/`View\Factory`.
- [ ] Uploads validated; stored with server-generated names.
- [ ] No open redirect (redirect targets validated).
- [ ] Production errors are generic (`APP_DEBUG=false`); no secrets in logs.
- [ ] Rate limiting not relied on for strict limits (file cache is non-atomic).

For a deeper pass, delegate to the **BXCode Security Reviewer** agent.

## 4. Composer-free / .env-free boot (the CI3-style promise)
Prove it still boots with no `vendor/` and no `.env`:
```bash
php -r 'require "bootstrap/autoload.php"; $a=\BXCode\Application::boot(getcwd());
require "routes/web.php";
echo $a->handle(new \BXCode\Http\Request("GET","/api/health"))->body(),"\n";'
```
Expect the health JSON. Also confirm `public/index.php`, `bxcode`, and
`public/check.php` all have the `vendor ? composer : bootstrap/autoload` fallback.

## 5. Benchmark (when performance is in scope)
```bash
php bin/benchmark.php 3000
```
Report mean/p50/p95 and peak memory. **Never** claim BXCode is faster than any
other framework — no comparative benchmark exists.

## 6. Report
Summarise: tests (counts), security findings (or "clean"), boot check result,
and anything you could NOT verify (e.g. real MySQL, live web SAPI) stated plainly.
