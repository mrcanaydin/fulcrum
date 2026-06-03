<?php

declare(strict_types=1);

namespace Fulcrum\Console;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\Migrations\MigrationCreator;
use Fulcrum\Database\Migrations\MigrationRepository;
use Fulcrum\Database\Migrations\Migrator;
use Fulcrum\Foundation\Application as Kernel;
use Throwable;

class Application
{
    public function __construct(private readonly Kernel $kernel) {}

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        try {
            return match ($command) {
                'migrate' => $this->migrate(),
                'migrate:rollback' => $this->rollback(),
                'migrate:status' => $this->status(),
                'make:migration' => $this->makeMigration($argv[2] ?? ''),
                'help', '--help', '-h' => $this->help(),
                default => $this->unknown($command),
            };
        } catch (Throwable $e) {
            fwrite(STDERR, "Error: {$e->getMessage()}" . PHP_EOL);
            return 1;
        }
    }

    private function migrate(): int
    {
        $ran = $this->migrator()->run($this->migrationPath());

        foreach ($ran as $migration) {
            $this->line("Migrated: {$migration}");
        }

        if ($ran === []) {
            $this->line('Nothing to migrate.');
        }

        return 0;
    }

    private function rollback(): int
    {
        $rolledBack = $this->migrator()->rollback($this->migrationPath());

        foreach ($rolledBack as $migration) {
            $this->line("Rolled back: {$migration}");
        }

        if ($rolledBack === []) {
            $this->line('Nothing to rollback.');
        }

        return 0;
    }

    private function status(): int
    {
        $rows = $this->migrator()->status($this->migrationPath());

        if ($rows === []) {
            $this->line('No migrations found.');
            return 0;
        }

        foreach ($rows as $row) {
            $state = $row['ran'] ? 'Ran' : 'Pending';
            $this->line(str_pad($state, 10) . $row['migration']);
        }

        return 0;
    }

    private function makeMigration(string $name): int
    {
        if ($name === '') {
            fwrite(STDERR, 'Migration name is required.' . PHP_EOL);
            return 1;
        }

        $path = (new MigrationCreator())->create($this->migrationPath(), $name);
        $this->line("Created: {$path}");

        return 0;
    }

    private function help(): int
    {
        $this->line('Fulcrum CLI');
        $this->line('  migrate             Run pending migrations');
        $this->line('  migrate:rollback    Roll back the last migration batch');
        $this->line('  migrate:status      Show migration status');
        $this->line('  make:migration name Create a new migration file');

        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, "Unknown command [{$command}]." . PHP_EOL);
        return 1;
    }

    private function migrator(): Migrator
    {
        $db = $this->kernel->container()->make(DatabaseManager::class);

        if (!$db instanceof DatabaseManager) {
            throw new \RuntimeException('Database manager is not registered.');
        }

        return new Migrator(new MigrationRepository($db));
    }

    private function migrationPath(): string
    {
        return $this->kernel->basePath('database/migrations');
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
