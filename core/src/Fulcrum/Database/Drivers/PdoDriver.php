<?php

declare(strict_types=1);

namespace Fulcrum\Database\Drivers;

use PDO;
use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\QueryBuilder;
use Fulcrum\Database\Grammar\SqlGrammar;
use Fulcrum\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Base driver class for PDO connections.
 */
abstract class PdoDriver implements ConnectionInterface
{
    private int $transactionLevel = 0;

    /** @var array<int, list<callable>> */
    private array $afterCommitCallbacks = [];

    public function __construct(
        protected PDO $pdo,
        protected string $prefix = ''
    ) {}

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this, new SqlGrammar()))->table($table);
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('Unable to begin database transaction.');
            }
        } else {
            $this->pdo->exec('SAVEPOINT ' . $this->savepointName($this->transactionLevel + 1));
        }

        $this->transactionLevel++;
        $this->afterCommitCallbacks[$this->transactionLevel] = [];
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            throw new RuntimeException('No active database transaction to commit.');
        }

        $level = $this->transactionLevel;
        $callbacks = $this->afterCommitCallbacks[$level] ?? [];
        unset($this->afterCommitCallbacks[$level]);

        if ($level === 1) {
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Unable to commit database transaction.');
            }
            $this->transactionLevel = 0;

            foreach ($callbacks as $callback) {
                $callback();
            }

            return;
        }

        $this->pdo->exec('RELEASE SAVEPOINT ' . $this->savepointName($level));
        $this->transactionLevel--;
        $this->afterCommitCallbacks[$this->transactionLevel] = [
            ...($this->afterCommitCallbacks[$this->transactionLevel] ?? []),
            ...$callbacks,
        ];
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel === 0) {
            throw new RuntimeException('No active database transaction to roll back.');
        }

        $level = $this->transactionLevel;
        unset($this->afterCommitCallbacks[$level]);

        if ($level === 1) {
            if (!$this->pdo->rollBack()) {
                throw new RuntimeException('Unable to roll back database transaction.');
            }
            $this->transactionLevel = 0;
            $this->afterCommitCallbacks = [];

            return;
        }

        $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $this->savepointName($level));
        $this->transactionLevel--;
    }

    public function transactionLevel(): int
    {
        return $this->transactionLevel;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->transactionLevel > 0) {
                $this->rollBack();
            }

            throw $exception;
        }
    }

    public function afterCommit(callable $callback): void
    {
        if ($this->transactionLevel === 0) {
            $callback();

            return;
        }

        $this->afterCommitCallbacks[$this->transactionLevel][] = $callback;
    }

    public function select(string $query, array $bindings = []): Collection
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $record = [];

                foreach ($row as $key => $value) {
                    if (is_string($key)) {
                        $record[$key] = $value;
                    }
                }

                $results[] = $record;
            }
        }

        return new Collection($results);
    }

    public function insert(string $query, array $bindings = []): int|string
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        $id = $this->pdo->lastInsertId();

        if ($id === false) {
            throw new RuntimeException('Unable to retrieve the inserted row ID.');
        }

        return $id;
    }

    public function update(string $query, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function delete(string $query, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /** @param array<mixed> $bindings */
    public function statement(string $query, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($bindings);
    }
    
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function savepointName(int $level): string
    {
        return 'fulcrum_' . $level;
    }
}
