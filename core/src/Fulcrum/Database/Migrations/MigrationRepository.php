<?php

declare(strict_types=1);

namespace Fulcrum\Database\Migrations;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\ConnectionInterface;

class MigrationRepository
{
    public const TABLE = 'migrations';

    public function __construct(private readonly DatabaseManager $db) {}

    public function ensureExists(): void
    {
        $connection = $this->db->connection();

        $connection->statement(
            'CREATE TABLE IF NOT EXISTS migrations (
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )'
        );
    }

    public function connection(): ConnectionInterface
    {
        return $this->db->connection();
    }

    /** @return list<string> */
    public function ran(): array
    {
        $this->ensureExists();

        $migrations = $this->db->table(self::TABLE)
            ->select('migration')
            ->orderBy('migration')
            ->get()
            ->pluck('migration')
            ->all();

        return $this->stringList($migrations);
    }

    public function log(string $migration, int $batch): void
    {
        $this->ensureExists();

        $this->db->table(self::TABLE)->insert([
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }

    public function delete(string $migration): void
    {
        $this->ensureExists();

        $this->db->table(self::TABLE)
            ->where('migration', $migration)
            ->delete();
    }

    public function nextBatchNumber(): int
    {
        $this->ensureExists();

        $row = $this->db->connection()
            ->select('SELECT MAX(batch) AS batch FROM migrations')
            ->first();

        $batch = is_array($row) && isset($row['batch']) && is_numeric($row['batch'])
            ? (int) $row['batch']
            : 0;

        return $batch + 1;
    }

    /** @return list<string> */
    public function lastBatch(): array
    {
        $this->ensureExists();

        $row = $this->db->connection()
            ->select('SELECT MAX(batch) AS batch FROM migrations')
            ->first();

        $batch = is_array($row) && isset($row['batch']) && is_numeric($row['batch'])
            ? (int) $row['batch']
            : 0;

        if ($batch === 0) {
            return [];
        }

        $migrations = $this->db->table(self::TABLE)
            ->select('migration')
            ->where('batch', $batch)
            ->orderBy('migration', 'desc')
            ->get()
            ->pluck('migration')
            ->all();

        return $this->stringList($migrations);
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
