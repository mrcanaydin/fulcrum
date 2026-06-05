<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Feature;

use Fulcrum\Container\Container;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\DatabaseServiceProvider;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\GraphQLServiceProvider;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Routing\Request;
use RuntimeException;

final class TestReliableMutations
{
    public static int $calls = 0;

    public function __construct(private readonly DatabaseManager $db) {}

    #[Mutation(name: 'failingWrite', type: 'Boolean', transactional: true)]
    public function failingWrite(): bool
    {
        $this->db->table('entries')->insert(['name' => 'partial']);

        throw new RuntimeException('fail after write');
    }

    #[Mutation(name: 'idempotentWrite', type: 'String!', idempotent: true)]
    #[Arg(name: 'name', type: 'String!')]
    public function idempotentWrite(mixed $root, array $args): string
    {
        self::$calls++;

        return (string) $this->db->table('entries')->insert(['name' => $args['name']]);
    }
}

/** @return array{0: Container, 1: DatabaseManager, 2: Executor} */
function reliableMutationApp(): array
{
    $container = new Container();
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $config->set('graphql.types', [TestReliableMutations::class]);
    $container->instance(Config::class, $config);

    (new DatabaseServiceProvider($container))->register();
    (new GraphQLServiceProvider($container))->register();

    $db = $container->make(DatabaseManager::class);
    $db->connection()->statement('CREATE TABLE entries (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');
    $db->connection()->statement(
        'CREATE TABLE idempotency_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            scope VARCHAR(64) NOT NULL,
            idempotency_key VARCHAR(255) NOT NULL,
            request_hash VARCHAR(64) NOT NULL,
            response TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            UNIQUE (scope, idempotency_key)
        )'
    );

    return [$container, $db, $container->make(Executor::class)];
}

test('transactional GraphQL mutations roll back failed resolver writes', function () {
    [$container, $db, $executor] = reliableMutationApp();
    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);

    $result = $executor->execute('mutation { failingWrite }', context: $context);

    expect($result)->toHaveKey('errors')
        ->and($db->table('entries')->get()->all())->toBe([]);
});

test('idempotent GraphQL mutations replay results and reject changed arguments', function () {
    TestReliableMutations::$calls = 0;
    [$container, $db, $executor] = reliableMutationApp();
    $request = new Request('POST', '/graphql', ['HTTP_IDEMPOTENCY_KEY' => 'create-entry-1'], []);
    $context = new RequestContext($request, $container);

    $first = $executor->execute('mutation { idempotentWrite(name: "first") }', context: $context);
    $replayed = $executor->execute('mutation { idempotentWrite(name: "first") }', context: $context);
    $conflict = $executor->execute('mutation { idempotentWrite(name: "changed") }', context: $context);

    expect($first)->not->toHaveKey('errors')
        ->and($replayed)->toBe($first)
        ->and(TestReliableMutations::$calls)->toBe(1)
        ->and($db->table('entries')->get()->all())->toHaveCount(1)
        ->and($conflict['errors'][0]['extensions']['code'])->toBe('IDEMPOTENCY_KEY_REUSED');
});

test('idempotent GraphQL mutations require an idempotency key', function () {
    [$container, $_db, $executor] = reliableMutationApp();
    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);

    $result = $executor->execute('mutation { idempotentWrite(name: "first") }', context: $context);

    expect($result['errors'][0]['extensions']['code'])->toBe('IDEMPOTENCY_KEY_REQUIRED');
});
