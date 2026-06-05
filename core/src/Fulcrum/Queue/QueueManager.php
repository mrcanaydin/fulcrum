<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Fulcrum\Container\Container;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\Queue\Queues\DatabaseQueue;
use Fulcrum\Queue\Queues\SyncQueue;
use InvalidArgumentException;

class QueueManager
{
    /** @var array<string, Queue> */
    private array $queues = [];

    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $db,
        ?JobRunner $runner = null,
    ) {
        $this->runner = $runner ?? new JobRunner(new Container());
    }

    private readonly JobRunner $runner;

    public function connection(?string $name = null): Queue
    {
        $name ??= $this->defaultConnection();

        return $this->queues[$name] ??= $this->make($name);
    }

    public function dispatch(Job $job, int $delaySeconds = 0): void
    {
        $this->connection()->push($job, $delaySeconds);
    }

    public function dispatchAfterCommit(Job $job, int $delaySeconds = 0, ?string $databaseConnection = null): void
    {
        $this->db->afterCommit(
            function () use ($job, $delaySeconds): void {
                $this->dispatch($job, $delaySeconds);
            },
            $databaseConnection,
        );
    }

    public function defaultConnection(): string
    {
        $default = $this->config->get('queue.default', 'sync');

        return is_string($default) && $default !== '' ? $default : 'sync';
    }

    /** @return array{pending: int, failed: int} */
    public function metrics(?string $name = null): array
    {
        $queue = $this->connection($name);

        return [
            'pending' => $queue->size(),
            'failed' => $queue->failedCount(),
        ];
    }

    public function retryFailed(?string $id = null, ?string $name = null): int
    {
        return $this->connection($name)->retryFailed($id);
    }

    /** @return list<array<string, mixed>> */
    public function failed(?string $id = null, ?string $name = null): array
    {
        return $this->connection($name)->failed($id);
    }

    private function make(string $name): Queue
    {
        $config = $this->config->get("queue.connections.{$name}", ['driver' => $name]);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Queue connection [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? $name;

        if (!is_string($driver)) {
            throw new InvalidArgumentException("Queue connection [{$name}] requires a string driver.");
        }

        return match ($driver) {
            'sync' => new SyncQueue($this->runner),
            'database' => new DatabaseQueue(
                $this->db,
                is_string($config['table'] ?? null) ? $config['table'] : 'jobs',
                is_string($config['queue'] ?? null) ? $config['queue'] : 'default',
                is_string($config['failed_table'] ?? null) ? $config['failed_table'] : 'failed_jobs',
                is_numeric($config['retry_after'] ?? null) ? max(1, (int) $config['retry_after']) : 90,
            ),
            default => throw new InvalidArgumentException("Unsupported queue driver [{$driver}]."),
        };
    }
}
