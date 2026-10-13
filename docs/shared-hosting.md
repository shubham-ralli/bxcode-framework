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

## No Composer, no .env (CodeIgniter-3 style)

BXCode runs with **no Composer and no `.env`** — upload the files and go.

- **No Composer / no `vendor/`.** `public/index.php`, `bxcode`, and
  `public/check.php` use Composer's autoloader when `vendor/` exists, and
  otherwise fall back to the built-in autoloader at `bootstrap/autoload.php`.
  The framework has no production package dependencies, so nothing else is
  needed. Just upload the project and point the document root at `public/`.

- **No `.env` file at all.** Every setting lives in `config/*.php` and you edit
  the values directly. For example, `config/database.php`:

  ```php
  'mysql' => [
      'driver'   => 'mysql',
      'host'     => 'localhost',
      'database' => 'my_database',
      'username' => 'my_user',
      'password' => 'my_password',
      'charset'  => 'utf8mb4',
  ],
  ```

  Set `'debug' => false` for production in `config/app.php` the same way.

This is the simplest path for basic shared hosts. The Composer workflow below is
optional and only buys you the optimised autoloader.

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
`storage/`, and `vendor/`.

This is less ideal than Layout A — prefer A whenever the host allows it.

### If `mod_rewrite` is unavailable

The router reads `REQUEST_URI`, so URLs of the form
`https://your-site/index.php/hello/world` work even without rewrite rules.

## Configuration

There is no `.env` — edit `config/*.php` directly. Set `'debug' => false` and
`'env' => 'production'` in `config/app.php`, and your real credentials in
`config/database.php`. Keep `config/` out of the web root (Layouts A and B both
do this).

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
