# Security

How BXCode protects applications by default, and what remains the application's
responsibility.

## Built-in protections

| Area | Protection |
|------|-----------|
| SQL injection | The query builder only ever sends values as bound parameters. Identifiers (table/column names) are validated against a strict pattern and rejected otherwise, so untrusted input cannot reach SQL in any position. Operators are whitelisted. |
| XSS | Views escape with `$this->e()` / the `e()` helper (`htmlspecialchars`, `ENT_QUOTES`, UTF-8). JSON responses are sent with `application/json`. |
| CSRF | `CsrfMiddleware` requires a valid token (`_token` field or `X-CSRF-Token` header) on POST/PUT/PATCH/DELETE. Tokens are per-session and compared with `hash_equals`. |
| Sessions | Secure cookie defaults (`HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS). The session id is regenerated on login (`SessionGuard`) to prevent fixation. |
| Passwords | `Hash` wraps `password_hash`/`password_verify` with rehash detection. Plaintext passwords are never stored or logged. |
| Path traversal | `LocalFilesystem` normalises paths and rejects any that escape the storage root, plus null bytes. View names are charset-validated. |
| File uploads | `UploadedFile::validate()` checks size and an extension allowlist; `store()` writes with a server-generated random name, never the client filename. |
| Error disclosure | In production (`APP_DEBUG=false`) unexpected exceptions return a generic 500; stack traces and messages are shown only in debug. Genuine errors are logged, not surfaced. |
| Log redaction | The logger redacts secret-looking context keys (`password`, `token`, `secret`, `authorization`, `api_key`). |
| Headers | `SecurityHeaders` middleware adds `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy`. |
| Rate limiting | `RateLimit` middleware + `RateLimiter` primitive throttle per client IP + path using the cache. |
| CORS | `Cors` middleware applies configurable origin/method/header rules and answers preflight. |

## The application's responsibility

- **Apply the security middleware.** CSRF, security headers, rate limiting, and
  CORS are middleware you attach (globally, per group, or per route) — they are
  not forced on, because APIs and web forms need different combinations. Attach
  what each route needs; do not leave write routes unprotected.
- **Validate redirect targets.** `Response::redirect()` sends wherever you tell
  it. Never pass an unvalidated user-supplied URL (open redirect) — allow only
  relative paths or an allowlist of hosts.
- **Trust proxy headers only when verified.** `Request::ip()` returns
  `REMOTE_ADDR` and does not trust `X-Forwarded-For`, which is spoofable unless
  your proxy is authenticated.
- **Set a strong rate-limit backend for strict limits.** The file cache limiter
  is not atomic under heavy concurrency (see the note in `RateLimiter`). Use an
  atomic backend (e.g. Redis) where exact limits matter.
- **Keep `config/` and `storage/` out of the web root.** Credentials live in
  `config/*.php` (there is no `.env`). The recommended layout (document root =
  `public/`) keeps them private; the fallback `.htaccess` blocks these paths
  when it cannot.
- **Deep MIME validation for uploads.** Extension + size checks are provided;
  add content/MIME sniffing for higher-risk upload flows.

## Reporting

This is a sample framework; there is no formal disclosure process. Treat the
above as the security contract when building on it.
