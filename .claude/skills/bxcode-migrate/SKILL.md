---
name: bxcode-migrate
description: Create, run, and roll back database migrations in the BXCode framework, and model a table with the query builder. Use when adding/altering a database table, creating a migration, running migrations, or seeding data in this repo.
---

# BXCode migrations & data access

## Create a migration
```bash
php bxcode make:migration create_posts_table
# -> database/migrations/<timestamp>_create_posts_table.php
```
Fill the returned anonymous class. Use `$db->driver()` for driver-specific SQL
(sqlite vs mysql), following the existing `create_users_table` migration:
```php
use BXCode\Database\Connection;
use BXCode\Database\Migration;

return new class extends Migration {
    public function up(Connection $db): void
    {
        $id = $db->driver() === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE posts (
            {$id},
            title VARCHAR(200) NOT NULL,
            body TEXT NULL,
            created_at VARCHAR(32) NULL
        )");
    }

    public function down(Connection $db): void
    {
        $db->statement('DROP TABLE IF EXISTS posts');
    }
};
```

## Run / roll back
```bash
php bxcode migrate            # applies pending migrations (batch-tracked)
php bxcode migrate:rollback   # reverses the most recent batch
```

## Seed data
No seeder command yet — seed with the query builder in a one-off script or a
`migrate`-style file:
```php
$db->table('posts')->insert(['title' => 'Hello', 'created_at' => gmdate('c')]);
```

## Query it (safe by default)
```php
use BXCode\Facades\DB;

DB::table('posts')->where('title', '=', $title)->orderBy('id','desc')->limit(10)->get();
DB::table('posts')->insertGetId([...]);
DB::table('posts')->where('id','=',$id)->update([...]);     // returns affected count
DB::table('posts')->paginate(perPage: 15, page: 1);
DB::transaction(fn() => /* multiple writes */);
```

## Rules
- Values are always bound — never concatenate request data into SQL.
- Keep `up`/`down` reversible.
- Test against sqlite `:memory:` (see `tests/Integration/MigratorTest.php`) and
  run `./vendor/bin/phpunit`.
- MySQL-specific SQL isn't exercised by the sqlite tests — call it out for
  real-DB verification instead of claiming it passed.
```bash
php bxcode migrate && php bxcode route:list
```
