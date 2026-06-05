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
use Fulcrum\GraphQL\Attributes\EnumType;
use Fulcrum\GraphQL\Attributes\InputField;
use Fulcrum\GraphQL\Attributes\InputObject;
use Fulcrum\GraphQL\Exceptions\NotFoundException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Logging\Loggers\FileLogger;
use Fulcrum\Routing\Request;
use Fulcrum\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Utils\AST;

/** @return list<array<string, mixed>> */
function graphqlLogRecords(string $path): array
{
    return array_values(array_filter(array_map(
        static fn (string $line): mixed => json_decode($line, true),
        explode("\n", trim((string) file_get_contents($path))),
    ), 'is_array'));
}

// ─── Dummy Classes for Testing ───────────────────────────────────────────────

#[ObjectType(name: 'User', description: 'A test user')]
class TestUser
{
    public function __construct(
        #[Field(type: 'ID!')]
        public string $id,
        #[Field(type: 'String!', deprecationReason: 'Use displayName instead.')]
        public string $name,
    ) {}

    #[Field(type: 'String!', description: 'Returns the user email')]
    public function email(): string
    {
        return strtolower(str_replace(' ', '.', $this->name)) . '@example.com';
    }
}

#[InputObject(name: 'ProfileInput', description: 'Profile data accepted by a test query.')]
class TestProfileInput
{
    #[InputField(type: 'String!')]
    public string $name;

    #[InputField(type: 'Int', defaultValue: 18)]
    public int $age;
}

#[EnumType(name: 'AccountStatus')]
enum TestAccountStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
}

class TestUppercaseScalar extends CustomScalarType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Uppercase',
            'serialize' => static fn (mixed $value): string => strtoupper((string) $value),
            'parseValue' => static fn (mixed $value): string => strtoupper((string) $value),
            'parseLiteral' => static fn (Node $node): string => strtoupper((string) AST::valueFromASTUntyped($node)),
        ]);
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

    #[Query(name: 'missingResource', type: 'String')]
    public function missingResource(): string
    {
        throw new NotFoundException();
    }

    #[Query(name: 'invalidInput', type: 'String')]
    public function invalidInput(): string
    {
        throw new ValidationException(['email' => ['Email is invalid.']]);
    }

    #[Query(name: 'internalFailure', type: 'String')]
    public function internalFailure(): string
    {
        throw new \RuntimeException('Database password leaked.');
    }

}

class TestTypedQueries
{
    #[Query(name: 'typedEcho', type: 'JSON!')]
    #[Arg(name: 'profile', type: 'ProfileInput!')]
    #[Arg(name: 'status', type: 'AccountStatus!')]
    #[Arg(name: 'date', type: 'Date!')]
    #[Arg(name: 'website', type: 'URL!')]
    #[Arg(name: 'amount', type: 'Decimal!')]
    #[Arg(name: 'tag', type: 'Uppercase!')]
    #[Arg(name: 'timestamp', type: 'DateTime!')]
    #[Arg(name: 'metadata', type: 'JSON!')]
    public function typedEcho(mixed $root, array $args): array
    {
        return [
            'profile' => $args['profile'],
            'status' => $args['status'] instanceof TestAccountStatus ? $args['status']->value : null,
            'date' => $args['date'],
            'website' => $args['website'],
            'amount' => $args['amount'],
            'tag' => $args['tag'],
            'timestamp' => $args['timestamp'],
            'metadata' => $args['metadata'],
        ];
    }
}

#[ObjectType(name: 'SafetyNode')]
class TestSafetyNode
{
    #[Field(type: 'String!')]
    public string $name = 'node';

    #[Field(type: 'SafetyNode')]
    public function child(): self
    {
        return $this;
    }
}

class TestSafetyQueries
{
    public static int $calls = 0;

    #[Query(name: 'safetyNode', type: 'SafetyNode!')]
    public function safetyNode(): TestSafetyNode
    {
        self::$calls++;

        return new TestSafetyNode();
    }

    #[Query(name: 'slowField', type: 'String!')]
    public function slowField(): string
    {
        self::$calls++;
        usleep(50_000);

        return 'slow';
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

test('GraphQL errors expose stable codes and request IDs while masking internal failures', function () {
    $container = new Container();
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [TestUser::class, TestQueries::class]);
    $config->set('app.debug', false);
    $container->instance(Config::class, $config);
    $logPath = tempnam(sys_get_temp_dir(), 'fulcrum-graphql-errors-');
    $container->instance(LoggerInterface::class, new FileLogger($logPath));

    (new GraphQLServiceProvider($container))->register();

    /** @var Executor $executor */
    $executor = $container->make(Executor::class);
    $request = (new Request('POST', '/graphql', [], []))->withAttribute('request_id', 'req-errors');
    $context = new RequestContext($request, $container);

    $notFound = $executor->execute('{ missingResource }', context: $context);
    expect($notFound['errors'][0]['extensions']['code'])->toBe('NOT_FOUND')
        ->and($notFound['errors'][0]['extensions']['requestId'])->toBe('req-errors');

    $validation = $executor->execute('{ invalidInput }', context: $context);
    expect($validation['errors'][0]['extensions']['code'])->toBe('VALIDATION_FAILED')
        ->and($validation['errors'][0]['extensions']['validation']['email'])->toBe(['Email is invalid.'])
        ->and($validation['errors'][0]['extensions']['requestId'])->toBe('req-errors');

    $internal = $executor->execute('{ internalFailure }', context: $context);
    expect($internal['errors'][0]['message'])->toBe('Internal server error')
        ->and($internal['errors'][0])->not->toHaveKey('debugMessage')
        ->and($internal['errors'][0]['extensions']['requestId'])->toBe('req-errors');

    $log = array_values(array_filter(
        graphqlLogRecords($logPath),
        static fn (array $record): bool => $record['message'] === 'GraphQL operation failed.',
    ))[0];
    expect($log['message'])->toBe('GraphQL operation failed.')
        ->and($log['context']['request_id'])->toBe('req-errors');

    unlink($logPath);
});

test('GraphQL supports input objects enums custom scalars built-in scalars and deprecation', function () {
    $container = new Container();
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [
        TestUser::class,
        TestProfileInput::class,
        TestAccountStatus::class,
        TestTypedQueries::class,
    ]);
    $config->set('graphql.scalars', [
        'Uppercase' => TestUppercaseScalar::class,
    ]);
    $container->instance(Config::class, $config);

    (new GraphQLServiceProvider($container))->register();

    /** @var Executor $executor */
    $executor = $container->make(Executor::class);
    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);
    $query = <<<'GQL'
    query Typed($profile: ProfileInput!, $status: AccountStatus!, $date: Date!, $website: URL!, $amount: Decimal!, $tag: Uppercase!, $timestamp: DateTime!, $metadata: JSON!) {
        typedEcho(profile: $profile, status: $status, date: $date, website: $website, amount: $amount, tag: $tag, timestamp: $timestamp, metadata: $metadata)
    }
    GQL;

    $result = $executor->execute($query, [
        'profile' => ['name' => 'Ada'],
        'status' => 'ACTIVE',
        'date' => '2026-06-05',
        'website' => 'https://example.com',
        'amount' => '123.4500',
        'tag' => 'graphql',
        'timestamp' => '2026-06-05T12:30:00Z',
        'metadata' => ['featured' => true],
    ], 'Typed', $context);

    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['typedEcho']['profile'])->toBe(['name' => 'Ada', 'age' => 18])
        ->and($result['data']['typedEcho']['status'])->toBe('active')
        ->and($result['data']['typedEcho']['amount'])->toBe('123.4500')
        ->and($result['data']['typedEcho']['tag'])->toBe('GRAPHQL')
        ->and($result['data']['typedEcho']['timestamp'])->toBe('2026-06-05T12:30:00+00:00')
        ->and($result['data']['typedEcho']['metadata'])->toBe(['featured' => true]);

    $invalid = $executor->execute($query, [
        'profile' => ['name' => 'Ada'],
        'status' => 'ACTIVE',
        'date' => '06/05/2026',
        'website' => 'https://example.com',
        'amount' => '10.00',
        'tag' => 'graphql',
        'timestamp' => '2026-06-05T12:30:00Z',
        'metadata' => [],
    ], 'Typed', $context);

    expect($invalid)->toHaveKey('errors')
        ->and($invalid['errors'][0]['message'])->toContain('Date must use the YYYY-MM-DD format.');

    $introspection = $executor->execute(
        '{ __type(name: "User") { fields(includeDeprecated: true) { name isDeprecated deprecationReason } } }',
        context: $context,
    );
    $nameField = array_values(array_filter(
        $introspection['data']['__type']['fields'],
        static fn (array $field): bool => $field['name'] === 'name',
    ))[0];

    expect($nameField['isDeprecated'])->toBeTrue()
        ->and($nameField['deprecationReason'])->toBe('Use displayName instead.');
});

test('GraphQL query safety rejects excessive documents before resolver execution', function () {
    TestSafetyQueries::$calls = 0;
    $container = new Container();
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [TestSafetyNode::class, TestSafetyQueries::class]);
    $config->set('graphql.security.max_depth', 1);
    $config->set('graphql.security.max_complexity', 3);
    $config->set('graphql.security.max_aliases', 1);
    $config->set('graphql.security.max_operations', 1);
    $config->set('graphql.security.introspection', false);
    $container->instance(Config::class, $config);

    (new GraphQLServiceProvider($container))->register();
    /** @var Executor $executor */
    $executor = $container->make(Executor::class);
    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);

    $aliases = $executor->execute('{ one: safetyNode { name } two: safetyNode { name } }', context: $context);
    expect($aliases['errors'][0]['extensions']['code'])->toBe('ALIAS_LIMIT_EXCEEDED')
        ->and(TestSafetyQueries::$calls)->toBe(0);

    $operations = $executor->execute('query One { safetyNode { name } } query Two { safetyNode { name } }', operationName: 'One', context: $context);
    expect($operations['errors'][0]['extensions']['code'])->toBe('OPERATION_LIMIT_EXCEEDED')
        ->and(TestSafetyQueries::$calls)->toBe(0);

    $depth = $executor->execute('{ safetyNode { child { child { name } } } }', context: $context);
    expect($depth['errors'][0]['extensions']['code'])->toBe('QUERY_DEPTH_EXCEEDED')
        ->and(TestSafetyQueries::$calls)->toBe(0);

    $complexity = $executor->execute('{ safetyNode { name child { name } } }', context: $context);
    expect($complexity['errors'][0]['extensions']['code'])->toBe('QUERY_COMPLEXITY_EXCEEDED')
        ->and(TestSafetyQueries::$calls)->toBe(0);

    $introspection = $executor->execute('{ __schema { queryType { name } } }', context: $context);
    expect($introspection['errors'][0]['extensions']['code'])->toBe('INTROSPECTION_DISABLED')
        ->and(TestSafetyQueries::$calls)->toBe(0);

    $syntax = $executor->execute('{ safetyNode ', context: $context);
    expect($syntax['errors'][0]['extensions']['code'])->toBe('GRAPHQL_VALIDATION_FAILED')
        ->and(TestSafetyQueries::$calls)->toBe(0);
});

test('GraphQL query safety measures complexity and rejects over-budget synchronous execution', function () {
    TestSafetyQueries::$calls = 0;
    $container = new Container();
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [TestSafetyNode::class, TestSafetyQueries::class]);
    $config->set('graphql.security.max_execution_ms', 1);
    $container->instance(Config::class, $config);
    $logPath = tempnam(sys_get_temp_dir(), 'fulcrum-graphql-safety-');
    $container->instance(LoggerInterface::class, new FileLogger($logPath));

    (new GraphQLServiceProvider($container))->register();
    /** @var Executor $executor */
    $executor = $container->make(Executor::class);
    $context = new RequestContext(new Request('POST', '/graphql', [], []), $container);
    $result = $executor->execute('query Slow { slowField }', operationName: 'Slow', context: $context);

    expect($result['errors'][0]['extensions']['code'])->toBe('EXECUTION_TIMEOUT')
        ->and(TestSafetyQueries::$calls)->toBe(1);

    $records = graphqlLogRecords($logPath);
    $log = $records[array_key_last($records)];
    expect($log['message'])->toBe('GraphQL operation completed.')
        ->and($log['context']['operation'])->toBe('Slow')
        ->and($log['context']['complexity'])->toBe(1)
        ->and($log['context']['status'])->toBe('ok')
        ->and($log['context']['duration_ms'])->toBeGreaterThan(1);

    unlink($logPath);
});

test('GraphQL resolver metrics identify slow resolvers and request IDs', function () {
    $container = new Container();
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('graphql.types', [TestSafetyNode::class, TestSafetyQueries::class]);
    $config->set('graphql.observability.slow_resolver_ms', 1);
    $container->instance(Config::class, $config);
    $logPath = tempnam(sys_get_temp_dir(), 'fulcrum-resolver-metrics-');
    $container->instance(LoggerInterface::class, new FileLogger($logPath));

    (new GraphQLServiceProvider($container))->register();
    $executor = $container->make(Executor::class);
    $request = (new Request('POST', '/graphql', [], []))->withAttribute('request_id', 'req-resolver');
    $executor->execute('{ slowField }', context: new RequestContext($request, $container));

    $record = array_values(array_filter(
        graphqlLogRecords($logPath),
        static fn (array $record): bool => $record['message'] === 'GraphQL resolver completed slowly.',
    ))[0];

    expect($record['level'])->toBe('warning')
        ->and($record['context']['resolver'])->toBe(TestSafetyQueries::class . '::slowField')
        ->and($record['context']['request_id'])->toBe('req-resolver')
        ->and($record['context']['slow'])->toBeTrue()
        ->and($record['context']['duration_ms'])->toBeGreaterThan(1);

    unlink($logPath);
});
