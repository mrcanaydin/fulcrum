<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\Foundation\Config;
use RuntimeException;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(NotificationManager::class, NotificationManager::class);
        $this->container->singleton(NotificationHookListener::class, NotificationHookListener::class);
    }

    public function boot(): void
    {
        $dispatcher = $this->container->make(EventDispatcher::class);

        if (!$dispatcher instanceof EventDispatcher) {
            throw new RuntimeException('Event dispatcher is not registered.');
        }

        $config = $this->container->make(Config::class);

        if (!$config instanceof Config) {
            return;
        }

        foreach ($this->hookEvents($config) as $event) {
            $dispatcher->listen($event, NotificationHookListener::class);
        }
    }

    /** @return list<string> */
    private function hookEvents(Config $config): array
    {
        $events = [];

        foreach (['notifications.hooks', 'notifications.mail_hooks'] as $key) {
            $hooks = $config->get($key, []);

            if (!is_array($hooks)) {
                continue;
            }

            foreach (array_keys($hooks) as $event) {
                if (is_string($event) && class_exists($event)) {
                    $events[] = $event;
                }
            }
        }

        return array_values(array_unique($events));
    }
}
