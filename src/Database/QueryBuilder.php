<?php

declare(strict_types=1);

namespace BXCode\Database;

/**
 * A small, safe fluent query builder.
 *
 *   $db->table('users')
 *       ->where('status', '=', 'active')
 *       ->orderBy('created_at', 'desc')
 *       ->limit(20)
 *       ->get();
 *
 * Values are always sent as bound parameters — never interpolated into SQL.
 * Identifiers (table and column names) are validated against a strict pattern
 * and rejected otherwise, so untrusted input cannot reach SQL in any position.
 */
final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{type:string,boolean:string,sql:string,bindings:list<mixed>}> */
    private array $wheres = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
    ) {
        $this->assertIdentifier($table);
    }

    /** @param string ...$columns */
    public function select(string ...$columns): self
    {
        $columns = $columns === [] ? ['*'] : $columns;
        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }
        $this->columns = array_values($columns);

        return $this;
    }

    public function where(string $column, string $operator, mixed $value, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);
        $operator = $this->assertOperator($operator);

        $this->wheres[] = [
            'type'     => 'basic',
            'boolean'  => $this->assertBoolean($boolean),
            'sql'      => "{$column} {$operator} ?",
            'bindings' => [$value],
        ];

        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        if ($values === []) {
            // IN () is invalid SQL; an empty set matches nothing.
            $this->wheres[] = ['type' => 'raw', 'boolean' => $this->assertBoolean($boolean), 'sql' => '0 = 1', 'bindings' => []];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'type'     => 'in',
            'boolean'  => $this->assertBoolean($boolean),
            'sql'      => "{$column} IN ({$placeholders})",
            'bindings' => array_values($values),
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->assertIdentifier($column);
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = "{$column} {$direction}";

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * Run the SELECT and return all matching rows.
     *
     * @return list<array<string,mixed>>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();

        return $this->connection->select($sql, $bindings);
    }

    /**
     * Run the SELECT and return the first row, or null.
     *
     * @return array<string,mixed>|null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        [$sql, $bindings] = $this->compileSelect();

        return $this->connection->selectOne($sql, $bindings);
    }

    /** Return a single column's value from the first row, or null. */
    public function value(string $column): mixed
    {
        $this->assertIdentifier($column);
        $row = $this->select($column)->first();

        return $row[$column] ?? null;
    }

    public function count(): int
    {
        $previousColumns = $this->columns;
        $this->columns   = ['COUNT(*) AS aggregate'];
        [$sql, $bindings] = $this->compileSelect(withOrderAndLimit: false);
        $this->columns   = $previousColumns;

        $row = $this->connection->selectOne($sql, $bindings);

        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Insert one row of [column => value]. Returns whether it succeeded.
     *
     * @param array<string,mixed> $values
     */
    public function insert(array $values): bool
    {
        [$sql, $bindings] = $this->compileInsert($values);

        return $this->connection->statement($sql, $bindings);
    }

    /**
     * Insert one row and return the new auto-increment id.
     *
     * @param array<string,mixed> $values
     */
    public function insertGetId(array $values): int
    {
        [$sql, $bindings] = $this->compileInsert($values);
        $this->connection->affectingStatement($sql, $bindings);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update matching rows. Returns the number affected.
     *
     * @param array<string,mixed> $values
     */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $sets     = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $this->assertIdentifier($column);
            $sets[]     = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql     .= $whereSql;
        $bindings = [...$bindings, ...$whereBindings];

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /** Delete matching rows. Returns the number affected. */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        [$whereSql, $bindings] = $this->compileWheres();

        return $this->connection->affectingStatement($sql . $whereSql, $bindings);
    }

    /**
     * Paginate the query. Returns the page of rows plus navigation metadata.
     *
     * @return array{data:list<array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);

        $total = $this->count();

        $this->limit($perPage)->offset(($page - 1) * $perPage);

        return [
            'data'         => $this->get(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string,mixed> $values
     * @return array{0:string,1:list<mixed>}
     */
    private function compileInsert(array $values): array
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = array_keys($values);
        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";

        return [$sql, array_values($values)];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function compileSelect(bool $withOrderAndLimit = true): array
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}";

        [$whereSql, $bindings] = $this->compileWheres();
        $sql .= $whereSql;

        if ($withOrderAndLimit && $this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($withOrderAndLimit && $this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return [$sql, $bindings];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $sql      = '';
        $bindings = [];
        foreach ($this->wheres as $i => $where) {
            $sql     .= ($i === 0 ? ' WHERE ' : " {$where['boolean']} ") . $where['sql'];
            $bindings = [...$bindings, ...$where['bindings']];
        }

        return [$sql, $bindings];
    }

    private function assertIdentifier(string $identifier): void
    {
        if ($identifier === '*') {
            return;
        }

        // Allow a bare name, table.column, name AS alias, and COUNT(*) AS alias.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*|\.\*)?( AS [A-Za-z_][A-Za-z0-9_]*)?$/', $identifier) === 1) {
            return;
        }
        if ($identifier === 'COUNT(*) AS aggregate') {
            return;
        }

        throw new \InvalidArgumentException("Invalid SQL identifier [{$identifier}].");
    }

    private function assertOperator(string $operator): string
    {
        $normalised = strtolower(trim($operator));
        if (!in_array($normalised, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Unsupported operator [{$operator}].");
        }

        return in_array($normalised, ['like', 'not like'], true) ? strtoupper($normalised) : $normalised;
    }

    private function assertBoolean(string $boolean): string
    {
        $boolean = strtoupper($boolean);

        return $boolean === 'OR' ? 'OR' : 'AND';
    }
}
