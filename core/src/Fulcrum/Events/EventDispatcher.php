<?php

declare(strict_types=1);

namespace Fulcrum\Events;

use Fulcrum\Container\Container;
use InvalidArgumentException;

class EventDispatcher
{
    /** @var array<string, list<callable|class-string>> */
    private array $listeners = [];

    public function __construct(private readonly Container $container) {}

    /** @param callable|class-string $listener */
    public function listen(string $event, callable|string $listener): void
    {
        if ($event === '') {
            throw new InvalidArgumentException('Event name cannot be empty.');
        }

        $this->listeners[$event] ??= [];
        $this->listeners[$event][] = $listener;
    }

    /**
     * @param object|string $event
     * @return list<mixed>
     */
    public function dispatch(object|string $event, mixed $payload = null): array
    {
        $eventName = is_object($event) ? $event::class : $event;
        $eventPayload = is_object($event) ? $event : $payload;
        $responses = [];

        foreach ($this->listenersFor($eventName) as $listener) {
            $responses[] = $this->callListener($listener, $eventPayload, $eventName);
        }

        return $responses;
    }

    /** @return array<string, list<callable|class-string>> */
    public function listeners(): array
    {
        return $this->listeners;
    }

    /** @return list<callable|class-string> */
    private function listenersFor(string $event): array
    {
        return [
            ...($this->listeners[$event] ?? []),
            ...($this->listeners['*'] ?? []),
        ];
    }

    /** @param callable|class-string $listener */
    private function callListener(callable|string $listener, mixed $payload, string $eventName): mixed
    {
        if (is_callable($listener)) {
            return $listener($payload, $eventName);
        }

        $instance = $this->container->make($listener);

        if (!is_object($instance) || !method_exists($instance, 'handle')) {
            throw new InvalidArgumentException("Event listener [{$listener}] must define a handle method.");
        }

        return $instance->handle($payload, $eventName);
    }
}
