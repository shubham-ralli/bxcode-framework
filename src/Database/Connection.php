<?php

declare(strict_types=1);

namespace BXCode\Database;

use Closure;
use PDO;
use PDOException;
use Throwable;

/**
 * A single PDO connection. Connects lazily, runs only prepared statements, and
 * exposes the small set of operations the query builder and migrator need.
 *
 * Supported drivers: mysql (and compatible MariaDB) and sqlite.
 */
final class Connection
{
    private ?PDO $pdo = null;

    private bool $logging = false;

    /** @var list<array{sql:string,bindings:array<int|string,mixed>,time_ms:float}> */
    private array $queryLog = [];

    private int $transactionLevel = 0;

    /** Slow-query threshold in milliseconds; queries at or above are flagged. */
    public float $slowQueryMs = 200.0;

    /**
     * @param array{
     *     driver?:string, host?:string, port?:int|string, database?:string,
     *     username?:string, password?:string, charset?:string,
     *     options?:array<int,mixed>
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /** The live PDO handle, connecting on first use. */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    private function connect(): PDO
    {
        $driver  = $this->driver();
        $options = ($this->config['options'] ?? []) + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return match ($driver) {
                'sqlite' => new PDO('sqlite:' . ($this->config['database'] ?? ':memory:'), null, null, $options),
                'mysql'  => new PDO(
                    $this->mysqlDsn(),
                    $this->config['username'] ?? null,
                    $this->config['password'] ?? null,
                    $options,
                ),
                default  => throw new \InvalidArgumentException("Unsupported database driver [{$driver}]."),
            };
        } catch (PDOException $e) {
            // Rethrow without the password-bearing DSN in the message.
            throw new \RuntimeException(
                "Could not connect to the [{$driver}] database: " . $e->getMessage(),
                (int) $e->getCode(),
            );
        }
    }

    private function mysqlDsn(): string
    {
        $parts = ['host=' . ($this->config['host'] ?? '127.0.0.1')];
        if (isset($this->config['port'])) {
            $parts[] = 'port=' . $this->config['port'];
        }
        if (isset($this->config['database'])) {
            $parts[] = 'dbname=' . $this->config['database'];
        }
        $parts[] = 'charset=' . ($this->config['charset'] ?? 'utf8mb4');

        return 'mysql:' . implode(';', $parts);
    }

    /**
     * Run a SELECT and return all rows.
     *
     * @param array<int|string,mixed> $bindings
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->run($sql, $bindings);

        /** @var list<array<string,mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Run a SELECT and return the first row, or null.
     *
     * @param array<int|string,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Run a write (INSERT/UPDATE/DELETE) and return the affected row count.
     *
     * @param array<int|string,mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * Run a statement that returns no rows and no count (DDL, etc).
     *
     * @param array<int|string,mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);

        return true;
    }

    /** The id of the last inserted row (driver-dependent). */
    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    /**
     * Run $callback inside a transaction, committing on success and rolling back
     * on any exception. Nested calls use savepoint-free counting (outermost
     * commits). Returns whatever the callback returns.
     *
     * @template T
     * @param Closure():T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo()->beginTransaction();
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo()->commit();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel > 0 && $this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
        $this->transactionLevel = 0;
    }

    /**
     * Prepare, bind, and execute a statement, recording it when logging is on.
     *
     * @param array<int|string,mixed> $bindings
     */
    private function run(string $sql, array $bindings): \PDOStatement
    {
        $start     = microtime(true);
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($this->normaliseBindings($bindings));

        if ($this->logging) {
            $this->queryLog[] = [
                'sql'      => $sql,
                'bindings' => $bindings,
                'time_ms'  => round((microtime(true) - $start) * 1000, 3),
            ];
        }

        return $statement;
    }

    /**
     * Booleans and nulls need explicit handling across drivers; everything else
     * binds as-is via positional/named parameters.
     *
     * @param array<int|string,mixed> $bindings
     * @return array<int|string,mixed>
     */
    private function normaliseBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 1 : 0;
            }
        }

        return $bindings;
    }

    public function enableQueryLog(): self
    {
        $this->logging = true;

        return $this;
    }

    public function disableQueryLog(): self
    {
        $this->logging = false;

        return $this;
    }

    /** @return list<array{sql:string,bindings:array<int|string,mixed>,time_ms:float}> */
    public function queryLog(): array
    {
        return $this->queryLog;
    }

    /** Logged queries at or above the slow-query threshold. */
    public function slowQueries(): array
    {
        return array_values(array_filter(
            $this->queryLog,
            fn (array $entry): bool => $entry['time_ms'] >= $this->slowQueryMs,
        ));
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /** Start a fluent query against a table. */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }
}
