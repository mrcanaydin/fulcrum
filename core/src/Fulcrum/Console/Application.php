<?php

declare(strict_types=1);

namespace Fulcrum\Console;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\Factories\FactoryCreator;
use Fulcrum\Database\ModelCreator;
use Fulcrum\Database\Migrations\MigrationCreator;
use Fulcrum\Database\Migrations\MigrationRepository;
use Fulcrum\Database\Migrations\Migrator;
use Fulcrum\Database\Seeders\SeederCreator;
use Fulcrum\Database\Seeders\SeederRunner;
use Fulcrum\Foundation\Application as Kernel;
use Fulcrum\GraphQL\ResourceCreator;
use Fulcrum\Queue\QueueWorker;
use Fulcrum\Schedule\ScheduledCommand;
use Fulcrum\Schedule\ScheduleRunner;
use Fulcrum\Support\Str;
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
                'db:seed' => $this->seed($argv[2] ?? ''),
                'schedule:run' => $this->runSchedule(),
                'queue:work' => $this->workQueue(array_slice($argv, 2)),
                'make:migration' => $this->makeMigration($argv[2] ?? ''),
                'make:model' => $this->makeModel($argv[2] ?? ''),
                'make:resource' => $this->makeResource($argv[2] ?? '', array_slice($argv, 3)),
                'make:seeder' => $this->makeSeeder($argv[2] ?? ''),
                'make:factory' => $this->makeFactory($argv[2] ?? ''),
                'help', '--help', '-h' => $this->help(),
                default => $this->runAppCommand($command, array_slice($argv, 2)),
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

    private function seed(string $class): int
    {
        $this->loadPhpFiles($this->factoryPath());
        $this->loadPhpFiles($this->seederPath());
        $class = $this->seederClass($class);
        $ran = (new SeederRunner($this->kernel->container()))->run($class);

        $this->line("Seeded: {$ran}");

        return 0;
    }

    private function makeSeeder(string $name): int
    {
        if ($name === '') {
            fwrite(STDERR, 'Seeder name is required.' . PHP_EOL);
            return 1;
        }

        $path = (new SeederCreator())->create($this->seederPath(), $name);
        $this->line("Created: {$path}");

        return 0;
    }

    private function makeModel(string $name): int
    {
        if ($name === '') {
            fwrite(STDERR, 'Model name is required.' . PHP_EOL);
            return 1;
        }

        $path = (new ModelCreator())->create($this->modelPath(), $name);
        $this->line("Created: {$path}");

        return 0;
    }

    /** @param list<string> $fields */
    private function makeResource(string $name, array $fields): int
    {
        if ($name === '') {
            fwrite(STDERR, 'Resource name is required.' . PHP_EOL);
            return 1;
        }

        if ($fields === []) {
            fwrite(STDERR, 'At least one field is required, e.g. title:string published:boolean.' . PHP_EOL);
            return 1;
        }

        $paths = (new ResourceCreator())->create($this->kernel->basePath(), $name, $fields);

        foreach ($paths as $path) {
            $this->line("Created: {$path}");
        }

        $model = (new ModelCreator())->className($name);
        $this->line("Register App\\GraphQL\\{$model}Type, {$model}Query, and {$model}Mutation in config/graphql.php.");

        return 0;
    }

    private function makeFactory(string $name): int
    {
        if ($name === '') {
            fwrite(STDERR, 'Factory name is required.' . PHP_EOL);
            return 1;
        }

        $path = (new FactoryCreator())->create($this->factoryPath(), $name);
        $this->line("Created: {$path}");

        return 0;
    }

    private function help(): int
    {
        $this->line('Fulcrum CLI');
        $this->line('  migrate             Run pending migrations');
        $this->line('  migrate:rollback    Roll back the last migration batch');
        $this->line('  migrate:status      Show migration status');
        $this->line('  db:seed [class]     Run database seeders');
        $this->line('  schedule:run        Run due scheduled commands');
        $this->line('  queue:work          Listen for queued jobs');
        $this->line('  make:migration name Create a new migration file');
        $this->line('  make:model name     Create a new model class');
        $this->line('  make:resource name fields... Create model and GraphQL CRUD classes');
        $this->line('  make:seeder name    Create a new seeder class');
        $this->line('  make:factory name   Create a new factory class');

        $registry = $this->commandRegistry();
        foreach ($registry->all() as $command) {
            $this->line('  ' . str_pad($command->name(), 20) . $command->description());
        }

        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, "Unknown command [{$command}]." . PHP_EOL);
        return 1;
    }

    /** @param list<string> $tokens */
    private function runAppCommand(string $command, array $tokens): int
    {
        $registry = $this->commandRegistry();

        if (!$registry->has($command)) {
            return $this->unknown($command);
        }

        return $registry->run($command, $tokens);
    }

    private function runSchedule(): int
    {
        $events = $this->scheduledEvents();
        $ran = (new ScheduleRunner($this->kernel))->run($events);

        foreach ($ran as $command) {
            $this->line("Scheduled: {$command}");
        }

        if ($ran === []) {
            $this->line('No scheduled commands are due.');
        }

        return 0;
    }

    /** @param list<string> $tokens */
    private function workQueue(array $tokens): int
    {
        $input = new Input($tokens);
        $worker = $this->kernel->container()->make(QueueWorker::class);

        if (!$worker instanceof QueueWorker) {
            throw new \RuntimeException('Queue worker is not registered.');
        }

        $processed = $worker->work(
            (int) $input->stringOption('max-jobs', '0'),
            (int) $input->stringOption('sleep', '1'),
            (int) $input->stringOption('tries', '3'),
        );

        $this->line("Processed jobs: {$processed}");

        return 0;
    }

    /** @return list<ScheduledCommand> */
    private function scheduledEvents(): array
    {
        $path = $this->kernel->basePath('config/schedule.php');

        if (!file_exists($path)) {
            return [];
        }

        $events = require $path;

        if (!is_array($events)) {
            return [];
        }

        return array_values(array_filter($events, fn (mixed $event): bool => $event instanceof ScheduledCommand));
    }

    private function commandRegistry(): CommandRegistry
    {
        $registry = $this->kernel->container()->make(CommandRegistry::class);

        if (!$registry instanceof CommandRegistry) {
            throw new \RuntimeException('Command registry is not registered.');
        }

        return $registry;
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

    private function modelPath(): string
    {
        return $this->kernel->basePath('src/Models');
    }

    private function seederPath(): string
    {
        return $this->kernel->basePath('database/seeders');
    }

    private function factoryPath(): string
    {
        return $this->kernel->basePath('database/factories');
    }

    private function seederClass(string $class): string
    {
        if ($class === '') {
            return 'Database\\Seeders\\DatabaseSeeder';
        }

        if (str_contains($class, '\\')) {
            return $class;
        }

        $class = Str::pascal($class);

        return 'Database\\Seeders\\' . (str_ends_with($class, 'Seeder') ? $class : $class . 'Seeder');
    }

    private function loadPhpFiles(string $path): void
    {
        foreach (glob(rtrim($path, '/') . '/*.php') ?: [] as $file) {
            require_once $file;
        }
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
