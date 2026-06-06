<?php

declare(strict_types=1);

namespace Fulcrum\Database\Drivers;

use PDOException;

/**
 * Postgres Driver implementation.
 */
class PostgresDriver extends PdoDriver
{
    public function insert(string $query, array $bindings = []): int|string
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        try {
            $id = $this->pdo->lastInsertId();

            return $id === false ? $stmt->rowCount() : $id;
        } catch (PDOException) {
            return $stmt->rowCount();
        }
    }
}
