<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Feature;

use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\GraphQLServiceProvider;
use Fulcrum\GraphQL\Attributes\Query;
use Fulcrum\GraphQL\Attributes\ObjectType;
use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Routing\Request;

// ─── Dummy Classes for Testing ───────────────────────────────────────────────

#[ObjectType(name: 'User', description: 'A test user')]
class TestUser
{
    public function __construct(
        #[Field(type: 'ID!')]
        public string $id,
        #[Field(type: 'String!')]
        public string $name,
    ) {}

    #[Field(type: 'String!', description: 'Returns the user email')]
    public function email(): string
    {
        return strtolower(str_replace(' ', '.', $this->name)) . '@example.com';
    }
}

class TestQueries
{
    #[Query(name: 'hello', type: 'String!')]
    public function hello(): string
    {
        return 'World';
    }

    #[Query(name: 'greet', type: 'String!')]
    #[Arg(name: 'name', type: 'String', defaultValue: 'Guest')]
    public function greet($root, array $args): string
    {
        return "Hello, {$args['name']}!";
    }

    #[Query(name: 'user', type: 'User')]
    #[Arg(name: 'id', type: 'ID!')]
    public function user($root, array $args): ?TestUser
    {
        if ($args['id'] === '1') {
            return new TestUser('1', 'Alice Smith');
        }
        return null;
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('GraphQL Engine resolves basic queries', function () {
    $container = new Container();
    
    // Setup config
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [
        TestUser::class,
        TestQueries::class,
    ]);
    $container->instance(Config::class, $config);

    // Register Service Provider
    $provider = new GraphQLServiceProvider($container);
    $provider->register();

    /** @var Executor $executor */
    $executor = $container->make(Executor::class);

    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);

    // Test simple hello world
    $result = $executor->execute('{ hello }', null, null, $context);
    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['hello'])->toBe('World');

    // Test with args & default value
    $result = $executor->execute('{ greet }', null, null, $context);
    expect($result['data']['greet'])->toBe('Hello, Guest!');

    $result = $executor->execute('{ greet(name: "Bob") }', null, null, $context);
    expect($result['data']['greet'])->toBe('Hello, Bob!');

    // Test object type resolution and method field
    $query = <<<'GQL'
    {
        user(id: "1") {
            id
            name
            email
        }
    }
    GQL;

    $result = $executor->execute($query, null, null, $context);
    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['user']['id'])->toBe('1')
        ->and($result['data']['user']['name'])->toBe('Alice Smith')
        ->and($result['data']['user']['email'])->toBe('alice.smith@example.com');
});
