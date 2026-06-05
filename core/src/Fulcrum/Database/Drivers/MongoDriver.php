<?php

declare(strict_types=1);

namespace Fulcrum\Database\Drivers;

use MongoDB\Client;
use MongoDB\Database;
use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\QueryBuilder;
use Fulcrum\Database\Grammar\MongoGrammar;
use Fulcrum\Support\Collection;
use RuntimeException;

/**
 * MongoDB Driver implementation using mongodb/mongodb.
 */
class MongoDriver implements ConnectionInterface
{
    private Database $db;

    public function __construct(
        protected Client $client,
        string $database
    ) {
        $this->db = $this->client->selectDatabase($database);
    }

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this, new MongoGrammar()))->table($table);
    }

    public function beginTransaction(): void
    {
        throw new RuntimeException('MongoDB transactions are not supported by this driver yet.');
    }

    public function commit(): void
    {
        throw new RuntimeException('MongoDB transactions are not supported by this driver yet.');
    }

    public function rollBack(): void
    {
        throw new RuntimeException('MongoDB transactions are not supported by this driver yet.');
    }

    public function transactionLevel(): int
    {
        return 0;
    }

    public function transaction(callable $callback): mixed
    {
        throw new RuntimeException('MongoDB transactions are not supported by this driver yet.');
    }

    public function afterCommit(callable $callback): void
    {
        $callback();
    }

    public function select(string $query, array $bindings = []): Collection
    {
        $command = json_decode($query, true);
        $collection = $this->db->selectCollection($command['table']);

        $filter = $this->applyBindings($command['filter'] ?? [], $bindings);
        $options = $command['options'] ?? [];

        $cursor = $collection->find($filter, $options);
        
        $results = [];
        foreach ($cursor as $document) {
            $array = (array) $document;
            if (isset($array['_id'])) {
                $array['id'] = (string) $array['_id'];
                unset($array['_id']);
            }
            $results[] = $array;
        }

        return new Collection($results);
    }

    public function insert(string $query, array $bindings = []): int|string
    {
        $command = json_decode($query, true);
        $collection = $this->db->selectCollection($command['table']);

        $document = $command['document'] ?? [];
        // Bindings map to document values logically in QueryBuilder
        // For Mongo we actually don't use '?' inside documents typically, we just pass the raw document.
        // Wait, compileInsert creates: 'document' => $values.
        // And QueryBuilder passes $bindings = array_values($values).
        // Since we have the full document in $command['document'], we don't need $bindings here.
        
        $result = $collection->insertOne($document);
        
        return (string) $result->getInsertedId();
    }

    public function update(string $query, array $bindings = []): int
    {
        $command = json_decode($query, true);
        $collection = $this->db->selectCollection($command['table']);

        // Bindings for update: array_merge(array_values($values), $whereBindings)
        // Since we already have the raw update array in $command['update'], we only need to apply bindings to the filter.
        $updateCount = count($command['update']['$set'] ?? []);
        
        $filterBindings = array_slice($bindings, $updateCount);
        $filter = $this->applyBindings($command['filter'] ?? [], $filterBindings);

        $result = $collection->updateMany($filter, $command['update']);

        return $result->getModifiedCount();
    }

    public function delete(string $query, array $bindings = []): int
    {
        $command = json_decode($query, true);
        $collection = $this->db->selectCollection($command['table']);

        $filter = $this->applyBindings($command['filter'] ?? [], $bindings);

        $result = $collection->deleteMany($filter);

        return $result->getDeletedCount();
    }

    public function statement(string $query, array $bindings = []): bool
    {
        // Not typically used for Mongo in the same way, but can execute raw commands
        $command = json_decode($query, true) ?? ['ping' => 1];
        $this->db->command($command);
        return true;
    }

    /**
     * Recursively replace '?' with bindings.
     */
    private function applyBindings(array $filter, array &$bindings): array
    {
        foreach ($filter as $key => &$value) {
            if (is_array($value)) {
                $value = $this->applyBindings($value, $bindings);
            } elseif ($value === '?') {
                $value = array_shift($bindings);
            }
        }

        return $filter;
    }
}
