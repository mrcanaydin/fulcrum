<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

use Fulcrum\Foundation\Config;
use Fulcrum\Notifications\Transports\LogNotificationTransport;
use Fulcrum\Notifications\Transports\WebhookNotificationTransport;
use Fulcrum\Queue\QueueManager;
use InvalidArgumentException;

class NotificationManager
{
    /** @var array<string, NotificationTransport> */
    private array $channels = [];

    public function __construct(
        private readonly Config $config,
        private readonly QueueManager $queues,
    ) {}

    public function channel(?string $name = null): NotificationTransport
    {
        $name ??= $this->defaultChannel();

        return $this->channels[$name] ??= $this->make($name);
    }

    public function send(Notification $notification, ?string $channel = null, ?bool $queue = null): void
    {
        $queue ??= (bool) $this->config->get('notifications.queue', true);

        if ($queue) {
            $this->queues->dispatch(new SendNotificationJob($notification, $channel));
            return;
        }

        $this->sendNow($notification, $channel);
    }

    public function sendNow(Notification $notification, ?string $channel = null): void
    {
        $this->channel($channel)->send($notification);
    }

    public function defaultChannel(): string
    {
        $default = $this->config->get('notifications.default', 'log');

        return is_string($default) && $default !== '' ? $default : 'log';
    }

    private function make(string $name): NotificationTransport
    {
        $config = $this->config->get("notifications.channels.{$name}", ['transport' => $name]);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Notification channel [{$name}] is not configured.");
        }

        $transport = $config['transport'] ?? $name;

        if (!is_string($transport)) {
            throw new InvalidArgumentException("Notification channel [{$name}] requires a string transport.");
        }

        return match ($transport) {
            'log' => new LogNotificationTransport($this->stringConfig($config, 'path', getcwd() . '/storage/logs/notifications.log')),
            'webhook' => new WebhookNotificationTransport(
                $this->stringConfig($config, 'url', ''),
                $this->nullableStringConfig($config, 'token'),
                $this->intConfig($config, 'timeout', 10),
            ),
            default => throw new InvalidArgumentException("Unsupported notification transport [{$transport}]."),
        };
    }

    /** @param array<string, mixed> $config */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** @param array<string, mixed> $config */
    private function nullableStringConfig(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $config */
    private function intConfig(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;

        return is_int($value) || is_string($value) || is_float($value) ? (int) $value : $default;
    }
}
