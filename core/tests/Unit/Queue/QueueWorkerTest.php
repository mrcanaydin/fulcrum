<?php

declare(strict_types=1);

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\Queue\Job;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Queue\QueueWorker;
use Fulcrum\Logging\Loggers\FileLogger;

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

class FulcrumTestFailingJob implements Job
{
    public function handle(): void
    {
        throw new \RuntimeException('expected queue failure');
    }
}

class FulcrumTestSlowJob implements Job
{
    public function handle(): void
    {
        sleep(2);
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
    $db->connection()->statement(
        'CREATE TABLE failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id VARCHAR(255) NOT NULL,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            failed_at INTEGER NOT NULL
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

it('claims database jobs once and recovers stale reservations', function () {
    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 10,
    ]);
    $queues = new QueueManager($config, $db);
    $queues->dispatch(new FulcrumTestQueuedJob('atomic'));

    $first = $queues->connection()->pop();
    $second = $queues->connection()->pop();

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();

    $db->table('jobs')->where('id', $first?->id ?? '')->update(['reserved_at' => time() - 20]);
    $recovered = $queues->connection()->pop();

    expect($recovered?->id)->toBe($first?->id)
        ->and($recovered?->attempts)->toBe(2);
});

it('releases failed jobs with backoff then moves terminal failures to dead letter storage', function () {
    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'failed_table' => 'failed_jobs',
        'queue' => 'default',
    ]);
    $queues = new QueueManager($config, $db);
    $queues->dispatch(new FulcrumTestFailingJob());

    (new QueueWorker($queues))->work(maxJobs: 1, sleepSeconds: 0, maxAttempts: 2, backoffSeconds: 2);
    $released = $db->table('jobs')->first();

    expect($released['attempts'] ?? null)->toBe(1)
        ->and(array_key_exists('reserved_at', $released))->toBeTrue()
        ->and($released['reserved_at'])->toBeNull()
        ->and((int) ($released['available_at'] ?? 0))->toBeGreaterThanOrEqual(time() + 1);

    $db->table('jobs')->where('id', (string) ($released['id'] ?? ''))->update(['available_at' => time()]);
    (new QueueWorker($queues))->work(maxJobs: 1, sleepSeconds: 0, maxAttempts: 2, backoffSeconds: 0);

    expect($queues->metrics())->toBe(['pending' => 0, 'failed' => 1])
        ->and($queues->failed())->toHaveCount(1)
        ->and($queues->failed()[0]['exception'] ?? '')->toContain('expected queue failure');

    expect($queues->retryFailed())->toBe(1)
        ->and($queues->metrics())->toBe(['pending' => 1, 'failed' => 0]);
});

it('dead letters jobs that exceed the configured timeout', function () {
    if (!function_exists('pcntl_alarm')) {
        $this->markTestSkipped('pcntl is required for queue timeout enforcement.');
    }

    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'failed_table' => 'failed_jobs',
        'queue' => 'default',
    ]);
    $queues = new QueueManager($config, $db);
    $queues->dispatch(new FulcrumTestSlowJob());

    (new QueueWorker($queues))->work(maxJobs: 1, sleepSeconds: 0, maxAttempts: 1, timeoutSeconds: 1);

    expect($queues->metrics())->toBe(['pending' => 0, 'failed' => 1])
        ->and($db->table('failed_jobs')->first()['exception'] ?? '')->toContain('JobTimeoutException');
});

it('logs worker duration and queue depth metrics', function () {
    $db = queueTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'database');
    $config->set('queue.connections.database', [
        'driver' => 'database',
        'table' => 'jobs',
        'failed_table' => 'failed_jobs',
        'queue' => 'default',
    ]);
    $queues = new QueueManager($config, $db);
    $queues->dispatch(new FulcrumTestQueuedJob('metrics'));
    $path = tempnam(sys_get_temp_dir(), 'fulcrum-queue-');

    (new QueueWorker($queues, logger: new FileLogger($path)))->work(maxJobs: 1, sleepSeconds: 0);

    $record = json_decode(trim((string) file_get_contents($path)), true);
    expect($record['message'] ?? null)->toBe('Queue job completed.')
        ->and($record['context']['queue_depth'] ?? null)->toBe(0)
        ->and($record['context']['failed_jobs'] ?? null)->toBe(0)
        ->and($record['context']['duration_ms'] ?? null)->toBeFloat();

    unlink($path);
});
