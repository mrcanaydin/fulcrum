<?php

declare(strict_types=1);

namespace Fulcrum\Database\Migrations;

use RuntimeException;

class Migrator
{
    public function __construct(private readonly MigrationRepository $repository) {}

    /** @return list<string> */
    public function run(string $path): array
    {
        $ran = $this->repository->ran();
        $pending = array_values(array_diff($this->files($path), $ran));
        $batch = $this->repository->nextBatchNumber();
        $migrated = [];

        foreach ($pending as $migration) {
            $instance = $this->resolve($path, $migration);
            $instance->up($this->repositoryConnection());
            $this->repository->log($migration, $batch);
            $migrated[] = $migration;
        }

        return $migrated;
    }

    /** @return list<string> */
    public function rollback(string $path): array
    {
        $rolledBack = [];

        foreach ($this->repository->lastBatch() as $migration) {
            $instance = $this->resolve($path, $migration);
            $instance->down($this->repositoryConnection());
            $this->repository->delete($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    /** @return list<array{migration: string, ran: bool}> */
    public function status(string $path): array
    {
        $ran = $this->repository->ran();

        return array_map(
            fn (string $migration): array => [
                'migration' => $migration,
                'ran' => in_array($migration, $ran, true),
            ],
            $this->files($path)
        );
    }

    /** @return list<string> */
    private function files(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = array_map(
            fn (string $file): string => basename($file, '.php'),
            glob(rtrim($path, '/') . '/*.php') ?: []
        );

        sort($files);

        return $files;
    }

    private function resolve(string $path, string $migration): Migration
    {
        $file = rtrim($path, '/') . '/' . $migration . '.php';
        $instance = require $file;

        if (!$instance instanceof Migration) {
            throw new RuntimeException("Migration [{$migration}] must return an instance of " . Migration::class . '.');
        }

        return $instance;
    }

    private function repositoryConnection(): \Fulcrum\Database\ConnectionInterface
    {
        return $this->repository->connection();
    }
}
