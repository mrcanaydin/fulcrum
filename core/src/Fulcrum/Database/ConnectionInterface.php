<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Support\Collection;

/**
 * Interface for database connection drivers.
 */
interface ConnectionInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function transactionLevel(): int;

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;

    /**
     * Run a callback after the outermost transaction commits, or immediately
     * when there is no active transaction.
     */
    public function afterCommit(callable $callback): void;

    /**
     * Start a new fluent query builder instance.
     */
    public function table(string $table): QueryBuilder;

    /**
     * Execute a SELECT query and return a collection of results.
     *
     * @param string $query (SQL or serialized Mongo command)
     * @param array<mixed> $bindings
     * @return Collection<array<string, mixed>>
     */
    public function select(string $query, array $bindings = []): Collection;

    /**
     * Execute an INSERT statement.
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return int|string The last insert ID
     */
    public function insert(string $query, array $bindings = []): int|string;

    /**
     * Execute an UPDATE statement.
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return int The number of affected rows
     */
    public function update(string $query, array $bindings = []): int;

    /**
     * Execute a DELETE statement.
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return int The number of affected rows
     */
    public function delete(string $query, array $bindings = []): int;

    /**
     * Execute a raw statement (like DDL).
     */
    /** @param array<mixed> $bindings */
    public function statement(string $query, array $bindings = []): bool;
}
