<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Feature;

use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\GraphQL\GraphQLServiceProvider;
use Fulcrum\Database\DatabaseServiceProvider;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Auth\AuthServiceProvider;
use Fulcrum\Auth\GraphQL\AuthTypes;
use Fulcrum\Auth\GraphQL\AuthQuery;
use Fulcrum\Auth\GraphQL\AuthMutation;
use Fulcrum\Auth\Attributes\RequiresAbility;
use Fulcrum\GraphQL\Attributes\Query;
use Fulcrum\GraphQL\Attributes\ObjectType;
use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\Routing\Request;

#[ObjectType(name: 'User')]
class DummyUser
{
    #[Field]
    public string $id;

    #[Field]
    public string $name;
}

class ProtectedQuery
{
    #[Query(name: 'adminData', type: 'String!')]
    #[RequiresAbility('admin:read')]
    public function adminData(): string
    {
        return 'Secret Data';
    }
}

test('Auth flow: create token, use it, revoke it', function () {
    $container = new Container();

    // 1. Setup Config
    $config = new Config(__DIR__ . '/non_existent');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
    $config->set('graphql.types', [
        DummyUser::class,
        \Fulcrum\Auth\GraphQL\TokenPayload::class,
        AuthQuery::class,
        AuthMutation::class,
        ProtectedQuery::class,
    ]);
    $config->set('app.debug', true);
    $container->instance(Config::class, $config);

    // 2. Register Services
    (new DatabaseServiceProvider($container))->register();
    (new AuthServiceProvider($container))->register();
    (new GraphQLServiceProvider($container))->register();

    $db = $container->make(DatabaseManager::class);

    // 3. Create Schema
    $db->connection()->statement("
        CREATE TABLE personal_access_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tokenable_type VARCHAR(255) NOT NULL,
            tokenable_id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL,
            abilities TEXT,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )
    ");
    
    $db->connection()->statement("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL
        )
    ");

    $userId = $db->connection()->insert("INSERT INTO users (name) VALUES ('Test User')", []);

    /** @var Executor $executor */
    $executor = $container->make(Executor::class);

    // Simulate an authenticated user asking to create a token
    // (In reality, they might log in first. Here we assume they are already logged in to create a token)
    $createTokenContext = new RequestContext(
        new Request('POST', '/graphql', [], []),
        $container,
        ['id' => $userId, '_table' => 'users']
    );

    $createMutation = <<<'GQL'
    mutation {
        createToken(name: "Test Token", abilities: ["*"]) {
            accessToken
            tokenType
            abilities
        }
    }
    GQL;

    $result = $executor->execute($createMutation, null, null, $createTokenContext);
    
    expect($result)->not->toHaveKey('errors');
    $token = $result['data']['createToken']['accessToken'];
    expect($token)->toBeString();

    // Now let's authenticate with that token via the TokenAuthenticator
    $requestWithToken = new Request('POST', '/graphql', ['HTTP_AUTHORIZATION' => "Bearer {$token}"], []);
    
    /** @var \Fulcrum\Auth\TokenAuthenticator $authenticator */
    $authenticator = $container->make(\Fulcrum\Auth\TokenAuthenticator::class);
    $authenticatedUser = $authenticator->authenticate($requestWithToken);

    expect($authenticatedUser)->not->toBeNull()
        ->and($authenticatedUser['id'])->toBe((string)$userId)
        ->and($authenticatedUser['_token']['name'])->toBe('Test Token');

    // Use token to query 'me'
    $authenticatedContext = new RequestContext($requestWithToken, $container, $authenticatedUser);
    
    $meQuery = <<<'GQL'
    query {
        me {
            id
            name
        }
    }
    GQL;

    $resultMe = $executor->execute($meQuery, null, null, $authenticatedContext);
    
    expect($resultMe)->not->toHaveKey('errors')
        ->and($resultMe['data']['me']['name'])->toBe('Test User');

    // Test ability requirements (Token has '*' so it should pass)
    $resultAdmin = $executor->execute('{ adminData }', null, null, $authenticatedContext);
    expect($resultAdmin)->not->toHaveKey('errors')
        ->and($resultAdmin['data']['adminData'])->toBe('Secret Data');

    // Revoke the token
    $revokeMutation = <<<'GQL'
    mutation($id: ID!) {
        revokeToken(tokenId: $id)
    }
    GQL;

    $tokenId = explode('|', $token)[0];
    $resultRevoke = $executor->execute($revokeMutation, ['id' => $tokenId], null, $authenticatedContext);
    
    expect($resultRevoke)->not->toHaveKey('errors')
        ->and($resultRevoke['data']['revokeToken'])->toBeTrue();

    // Ensure it no longer authenticates
    $shouldBeNull = $authenticator->authenticate($requestWithToken);
    expect($shouldBeNull)->toBeNull();
});
