<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Subscriptions;

use Fulcrum\Foundation\Config;

class SubscriptionEventPublisher
{
    public function __construct(
        private readonly Config $config,
        private readonly SubscriptionBroker $broker,
    ) {}

    public function handle(mixed $event, string $eventName): void
    {
        $mappings = $this->config->get('subscriptions.publish', []);
        $topics = is_array($mappings) ? ($mappings[$eventName] ?? []) : [];

        if (!is_array($topics)) {
            return;
        }

        $payload = is_object($event) ? get_object_vars($event) : ['value' => $event];

        foreach ($topics as $topic) {
            if (is_string($topic) && $topic !== '') {
                $this->broker->publish($topic, $payload);
            }
        }
    }
}
