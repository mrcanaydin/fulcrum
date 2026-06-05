<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Database;

use Fulcrum\Container\Container;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\Foundation\Config;
use Fulcrum\Queue\Job;
use Fulcrum\Queue\QueueManager;
use RuntimeException;

final class TransactionTestJob implements Job
{
    public function handle(): void
    {
        $GLOBALS['transaction_test_jobs'] = ((int) ($GLOBALS['transaction_test_jobs'] ?? 0)) + 1;
    }
}

function transactionTestDatabase(): DatabaseManager
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db = new DatabaseManager($config);
    $db->connection()->statement('CREATE TABLE entries (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');

    return $db;
}

it('commits successful transactions and rolls back failures', function () {
    $db = transactionTestDatabase();

    $result = $db->transaction(function () use ($db): string {
        $db->table('entries')->insert(['name' => 'committed']);

        return 'done';
    });

    expect($result)->toBe('done')
        ->and($db->table('entries')->get()->all())->toHaveCount(1);

    expect(fn () => $db->transaction(function () use ($db): void {
        $db->table('entries')->insert(['name' => 'rolled back']);
        throw new RuntimeException('stop');
    }))->toThrow(RuntimeException::class, 'stop')
        ->and($db->table('entries')->get()->all())->toHaveCount(1);
});

it('supports nested transactions and only runs callbacks after outer commit', function () {
    $db = transactionTestDatabase();
    $callbacks = [];

    $db->transaction(function () use ($db, &$callbacks): void {
        $db->afterCommit(function () use (&$callbacks): void {
            $callbacks[] = 'outer';
        });

        $db->transaction(function () use ($db, &$callbacks): void {
            $db->table('entries')->insert(['name' => 'nested']);
            $db->afterCommit(function () use (&$callbacks): void {
                $callbacks[] = 'inner';
            });
        });

        expect($callbacks)->toBe([]);
    });

    expect($callbacks)->toBe(['outer', 'inner']);
});

it('discards after-commit events and jobs when a transaction rolls back', function () {
    $db = transactionTestDatabase();
    $config = new Config(__DIR__ . '/missing');
    $config->set('queue.default', 'sync');
    $events = new EventDispatcher(new Container(), $db);
    $queues = new QueueManager($config, $db);
    $handledEvents = 0;
    $GLOBALS['transaction_test_jobs'] = 0;

    $events->listen('created', function () use (&$handledEvents): void {
        $handledEvents++;
    });

    expect(fn () => $db->transaction(function () use ($events, $queues): void {
        $events->dispatchAfterCommit('created');
        $queues->dispatchAfterCommit(new TransactionTestJob());
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback')
        ->and($handledEvents)->toBe(0)
        ->and($GLOBALS['transaction_test_jobs'])->toBe(0);

    $db->transaction(function () use ($events, $queues): void {
        $events->dispatchAfterCommit('created');
        $queues->dispatchAfterCommit(new TransactionTestJob());
    });

    expect($handledEvents)->toBe(1)
        ->and($GLOBALS['transaction_test_jobs'])->toBe(1);
});
