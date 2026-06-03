<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Events;

use Fulcrum\Container\Container;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\Events\EventServiceProvider;
use Fulcrum\Foundation\Config;

final class EventDispatcherTestEvent
{
    public function __construct(public readonly string $name) {}
}

final class EventDispatcherTestListener
{
    /** @var list<string> */
    public static array $handled = [];

    public function handle(EventDispatcherTestEvent $event): string
    {
        self::$handled[] = $event->name;

        return strtoupper($event->name);
    }
}

it('dispatches object events to callable listeners', function () {
    $dispatcher = new EventDispatcher(new Container());
    $seen = [];

    $dispatcher->listen(EventDispatcherTestEvent::class, function (EventDispatcherTestEvent $event, string $eventName) use (&$seen): string {
        $seen = [$eventName, $event->name];

        return 'handled';
    });

    $responses = $dispatcher->dispatch(new EventDispatcherTestEvent('fulcrum'));

    expect($responses)->toBe(['handled'])
        ->and($seen)->toBe([EventDispatcherTestEvent::class, 'fulcrum']);
});

it('resolves class listeners through the container', function () {
    EventDispatcherTestListener::$handled = [];
    $dispatcher = new EventDispatcher(new Container());
    $dispatcher->listen(EventDispatcherTestEvent::class, EventDispatcherTestListener::class);

    $responses = $dispatcher->dispatch(new EventDispatcherTestEvent('api'));

    expect($responses)->toBe(['API'])
        ->and(EventDispatcherTestListener::$handled)->toBe(['api']);
});

it('dispatches string events with payloads and wildcard listeners', function () {
    $dispatcher = new EventDispatcher(new Container());
    $events = [];

    $dispatcher->listen('api.requested', function (array $payload): string {
        return $payload['path'];
    });
    $dispatcher->listen('*', function (mixed $_payload, string $eventName) use (&$events): void {
        $events[] = $eventName;
    });

    $responses = $dispatcher->dispatch('api.requested', ['path' => '/graphql']);

    expect($responses[0])->toBe('/graphql')
        ->and($events)->toBe(['api.requested']);
});

it('registers configured listeners during provider boot', function () {
    EventDispatcherTestListener::$handled = [];
    $container = new Container();
    $config = new Config(__DIR__ . '/missing');
    $config->set('events.listeners', [
        EventDispatcherTestEvent::class => [
            EventDispatcherTestListener::class,
        ],
    ]);
    $container->instance(Config::class, $config);

    $provider = new EventServiceProvider($container);
    $provider->register();
    $provider->boot();

    $dispatcher = $container->make(EventDispatcher::class);

    expect($dispatcher)->toBeInstanceOf(EventDispatcher::class);

    $dispatcher->dispatch(new EventDispatcherTestEvent('booted'));

    expect(EventDispatcherTestListener::$handled)->toBe(['booted']);
});
