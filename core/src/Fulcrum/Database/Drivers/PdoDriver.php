<?php

declare(strict_types=1);

namespace Fulcrum\Database\Drivers;

use PDO;
use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\QueryBuilder;
use Fulcrum\Database\Grammar\SqlGrammar;
use Fulcrum\Support\Collection;

/**
 * Base driver class for PDO connections.
 */
abstract class PdoDriver implements ConnectionInterface
{
    public function __construct(
        protected PDO $pdo,
        protected string $prefix = ''
    ) {}

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this, new SqlGrammar()))->table($table);
    }

    public function select(string $query, array $bindings = []): Collection
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        return new Collection($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function insert(string $query, array $bindings = []): int|string
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        return $this->pdo->lastInsertId();
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

    public function statement(string $query, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($bindings);
    }
    
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
