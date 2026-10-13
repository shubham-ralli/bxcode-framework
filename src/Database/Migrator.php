<?php

declare(strict_types=1);

namespace BXCode\Database;

/**
 * Runs and reverses migrations, tracking applied ones in a `migrations` table
 * so each runs once. Migrations are PHP files in a directory, each returning a
 * Migration instance; they apply in ascending filename order (so the timestamp
 * prefix in `2026_01_01_120000_create_users_table.php` orders them).
 */
final class Migrator
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Apply every migration in $dir that has not run yet. Returns the names
     * applied, in order. Each batch gets the next batch number for rollback.
     *
     * @return list<string>
     */
    public function run(string $dir): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedNames();
        $batch   = $this->nextBatch();
        $ran     = [];

        foreach ($this->files($dir) as $name => $path) {
            if (in_array($name, $applied, true)) {
                continue;
            }

            $this->load($path)->up($this->db);
            $this->db->table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * Reverse the most recent batch. Returns the names rolled back.
     *
     * @return list<string>
     */
    public function rollback(string $dir): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->latestBatch();
        if ($batch === 0) {
            return [];
        }

        $rows = $this->db->table('migrations')
            ->where('batch', '=', $batch)
            ->orderBy('id', 'desc')
            ->get();

        $files      = $this->files($dir);
        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            if (isset($files[$name])) {
                $this->load($files[$name])->down($this->db);
            }
            $this->db->table('migrations')->where('migration', '=', $name)->delete();
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /** @return list<string> */
    public function appliedNames(): array
    {
        $this->ensureMigrationsTable();

        return array_map(
            static fn (array $row): string => (string) $row['migration'],
            $this->db->table('migrations')->orderBy('id', 'asc')->get(),
        );
    }

    public function ensureMigrationsTable(): void
    {
        $id = $this->db->driver() === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $this->db->statement(
            "CREATE TABLE IF NOT EXISTS migrations ({$id}, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)",
        );
    }

    /**
     * Migration files keyed by name (basename without .php), sorted ascending.
     *
     * @return array<string,string>
     */
    private function files(string $dir): array
    {
        $files = [];
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }
        ksort($files);

        return $files;
    }

    private function load(string $path): Migration
    {
        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration [{$path}] must return a " . Migration::class . ' instance.');
        }

        return $migration;
    }

    private function nextBatch(): int
    {
        return $this->latestBatch() + 1;
    }

    private function latestBatch(): int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) AS batch FROM migrations');

        return (int) ($row['batch'] ?? 0);
    }
}
