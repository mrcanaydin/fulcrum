<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\GraphQL;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ForbiddenException;
use Fulcrum\GraphQL\Exceptions\UnauthenticatedException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\GraphQL\Subscriptions\SubscriptionAuthorizer;
use Fulcrum\GraphQL\Subscriptions\SubscriptionAuthorizationHook;
use Fulcrum\GraphQL\Subscriptions\SubscriptionBroker;
use Fulcrum\GraphQL\Subscriptions\SubscriptionEventPublisher;
use Fulcrum\Routing\Request;
use Fulcrum\Container\Container;

function subscriptionConfig(): Config
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
    $config->set('subscriptions.table', 'subscription_events');
    $config->set('subscriptions.topics', [
        'private' => ['authenticated' => true, 'abilities' => ['events:read']],
        'user.created' => ['authenticated' => true, 'abilities' => ['events:read']],
    ]);

    return $config;
}

class RejectSubscriptionHook implements SubscriptionAuthorizationHook
{
    public function authorize(string $topic, RequestContext $context): bool
    {
        return false;
    }
}

it('publishes and resumes subscription events by cursor', function () {
    $config = subscriptionConfig();
    $db = new DatabaseManager($config);
    $db->connection()->statement('CREATE TABLE subscription_events (id INTEGER PRIMARY KEY AUTOINCREMENT, topic TEXT, payload TEXT, created_at INTEGER)');
    $broker = new SubscriptionBroker($db, $config);
    $first = $broker->publish('private', ['id' => '1']);
    $broker->publish('private', ['id' => '2']);

    expect($broker->events('private', $first))->toHaveCount(1)
        ->and($broker->events('private', $first)[0]['payload']['id'])->toBe('2');
});

it('authorizes subscription topics using authentication and abilities', function () {
    $config = subscriptionConfig();
    $container = new Container();
    $authorizer = new SubscriptionAuthorizer($config, $container);
    $request = new Request('GET', '/graphql/stream?topic=private');

    expect(fn () => $authorizer->authorize('private', new RequestContext($request, $container)))
        ->toThrow(UnauthenticatedException::class);
    expect(fn () => $authorizer->authorize('private', new RequestContext($request, $container, ['_token' => ['abilities' => []]])))
        ->toThrow(ForbiddenException::class);

    $authorizer->authorize('private', new RequestContext($request, $container, ['_token' => ['abilities' => ['events:read']]]));
    $authorizer->authorize('user.created', new RequestContext($request, $container, ['_token' => ['abilities' => ['events:read']]]));
    expect(true)->toBeTrue();
});

it('runs a custom subscription authorizer without static abilities', function () {
    $config = subscriptionConfig();
    $config->set('subscriptions.topics.custom', ['authorizer' => RejectSubscriptionHook::class]);
    $container = new Container();
    $authorizer = new SubscriptionAuthorizer($config, $container);
    $request = new Request('GET', '/graphql/stream?topic=custom');

    expect(fn () => $authorizer->authorize('custom', new RequestContext($request, $container)))
        ->toThrow(ForbiddenException::class);
});

it('publishes configured event names and dotted topics using exact keys', function () {
    $config = subscriptionConfig();
    $config->set('subscriptions.publish', ['domain.user.created' => ['user.created']]);
    $db = new DatabaseManager($config);
    $db->connection()->statement('CREATE TABLE subscription_events (id INTEGER PRIMARY KEY AUTOINCREMENT, topic TEXT, payload TEXT, created_at INTEGER)');
    $broker = new SubscriptionBroker($db, $config);
    $publisher = new SubscriptionEventPublisher($config, $broker);

    $publisher->handle((object) ['id' => '42'], 'domain.user.created');

    expect($broker->events('user.created'))->toHaveCount(1)
        ->and($broker->events('user.created')[0]['payload']['id'])->toBe('42');
});
