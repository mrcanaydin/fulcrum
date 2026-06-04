<?php

declare(strict_types=1);

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\Queue\Job;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Queue\QueueWorker;

class FulcrumTestQueuedJob implements Job
{
    public function __construct(private readonly string $value) {}

    public function handle(): void
    {
        $queue = $GLOBALS['fulcrum_test_queue'] ?? [];
        $queue = is_array($queue) ? $queue : [];
        $queue[] = $this->value;
        $GLOBALS['fulcrum_test_queue'] = $queue;
    }
}

function queueTestDatabase(): DatabaseManager
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
    ]);

    $db = new DatabaseManager($config);
    $db->connection()->statement(
        'CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )'
    );

    return $db;
}

it('processes database queued jobs', function () {
    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
    ]);
    $GLOBALS['fulcrum_test_queue'] = [];

    $queues = new QueueManager($config, $db);
    $queues->dispatch(new FulcrumTestQueuedJob('new'));
    $processed = (new QueueWorker($queues))->work(maxJobs: 1, sleepSeconds: 0);

    expect($processed)->toBe(1)
        ->and($GLOBALS['fulcrum_test_queue'])->toBe(['new'])
        ->and($db->table('jobs')->get()->all())->toBe([]);
});

it('stops bounded workers when no job is available', function () {
    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
    ]);

    $processed = (new QueueWorker(new QueueManager($config, $db)))->work(maxJobs: 1, sleepSeconds: 0);

    expect($processed)->toBe(0);
});
