<?php

declare(strict_types=1);

namespace Fulcrum\Events;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Foundation\Config;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(EventDispatcher::class, EventDispatcher::class);
    }

    public function boot(): void
    {
        $dispatcher = $this->container->make(EventDispatcher::class);

        if (!$dispatcher instanceof EventDispatcher) {
            throw new \RuntimeException('Event dispatcher is not registered.');
        }

        $config = $this->container->make(Config::class);

        if (!$config instanceof Config) {
            return;
        }

        $configured = $config->get('events.listeners', []);

        if (!is_array($configured)) {
            return;
        }

        foreach ($configured as $event => $listeners) {
            if (!is_string($event) || !is_array($listeners)) {
                continue;
            }

            foreach ($listeners as $listener) {
                if (is_string($listener) && class_exists($listener)) {
                    $dispatcher->listen($event, $listener);
                }
            }
        }
    }
}
