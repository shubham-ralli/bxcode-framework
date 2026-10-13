# Deploying BXCode to shared hosting

BXCode is designed for ordinary PHP hosting: no Node.js, no Redis, no background
workers, and no required CLI access at runtime. This guide covers Apache and
LiteSpeed hosts.

## Requirements

- PHP 8.3+ with the `json`, `mbstring`, and `PDO` extensions.
- `pdo_mysql` if you use MySQL/MariaDB.
- A writable `storage/` directory.

Upload the app and open `https://your-site/check.php` (or run
`php public/check.php`) to verify all of these before going further. Delete
`check.php` once setup is complete.

## Build the production package

On your development machine (the host needs no Composer):

```bash
composer install --no-dev --optimize-autoloader
php bin/package.php          # writes dist/bxcode.zip (bundles vendor/)
composer install             # restore dev dependencies
```

Upload and extract `dist/bxcode.zip` on the host.

## Layout A — document root points at `public/` (preferred)

Point your domain's document root (or create the site) at the `public/`
directory. Nothing else is web-accessible, which is the safest arrangement.

```
/home/you/bxcode/        <- project (not web-accessible)
/home/you/bxcode/public/ <- document root
```

`public/.htaccess` already routes all requests to the front controller and sets
basic security headers. It works unchanged on Apache (with `mod_rewrite`) and
LiteSpeed.

## Layout B — fixed document root (fallback)

If the host won't let you change the document root (everything is served from
one `public_html`), put the whole project there. The project-root `.htaccess`
forwards requests into `public/` and blocks direct access to `config/`, `src/`,
`storage/`, `vendor/`, and `.env`.

This is less ideal than Layout A — prefer A whenever the host allows it.

### If `mod_rewrite` is unavailable

The router reads `REQUEST_URI`, so URLs of the form
`https://your-site/index.php/hello/world` work even without rewrite rules.

## Configuration

```bash
cp .env.example .env
```

Edit `.env`: set `APP_ENV=production`, `APP_DEBUG=false`, and your `DB_*`
credentials. Keep `.env` out of the web root (Layouts A and B both do this).

## Permissions

`storage/` and its subdirectories must be writable by the web server:

```bash
chmod -R 775 storage
```

(Some hosts require `755` plus correct ownership; the setup checker reports
whether storage is writable.)

## Migrations

If the host has CLI/SSH access:

```bash
php bxcode migrate
```

If it does not, run migrations from a machine that can reach the database (e.g.
your local CLI pointed at the production `DB_*` credentials), or import the SQL
manually. BXCode does **not** require CLI at runtime — only for this one-off
step.

## Honest limitations

- Only Apache and LiteSpeed rewrite rules are provided. Nginx hosts need an
  equivalent `try_files` rule (not shipped).
- Hosts without `mod_rewrite` require the `/index.php/...` URL style.
- MySQL is supported via `pdo_mysql`; other databases are not supported yet.
- We do not claim universal shared-hosting compatibility. Run `check.php` on
  your specific host to confirm.
