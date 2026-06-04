<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

use Fulcrum\Foundation\Config;
use Fulcrum\Mail\MailManager;
use Fulcrum\Mail\Message;
use Fulcrum\Mail\SendMailJob;
use Fulcrum\Queue\QueueManager;

class NotificationHookListener
{
    public function __construct(
        private readonly Config $config,
        private readonly NotificationManager $notifications,
        private readonly MailManager $mail,
        private readonly QueueManager $queues,
    ) {}

    public function handle(mixed $event, string $eventName): void
    {
        $this->sendNotifications($event, $eventName);
        $this->sendMail($event, $eventName);
    }

    private function sendNotifications(mixed $event, string $eventName): void
    {
        foreach ($this->hooks('notifications.hooks', $eventName) as $hook) {
            if (!$this->enabled($hook)) {
                continue;
            }

            $this->notifications->send(new Notification(
                to: $this->template($this->string($hook, 'to'), $event),
                title: $this->template($this->string($hook, 'title'), $event),
                body: $this->template($this->string($hook, 'body'), $event),
                data: $this->templateArray($this->array($hook, 'data'), $event),
                headers: $this->stringArray($this->array($hook, 'headers'), $event),
            ), $this->nullableString($hook, 'channel'), $this->nullableBool($hook, 'queue'));
        }
    }

    private function sendMail(mixed $event, string $eventName): void
    {
        foreach ($this->hooks('notifications.mail_hooks', $eventName) as $hook) {
            if (!$this->enabled($hook)) {
                continue;
            }

            $message = new Message(
                to: $this->template($this->string($hook, 'to'), $event),
                subject: $this->template($this->string($hook, 'subject'), $event),
                text: $this->template($this->string($hook, 'text'), $event),
                html: $this->nullableTemplate($this->nullableString($hook, 'html'), $event),
                from: $this->nullableTemplate($this->nullableString($hook, 'from'), $event),
            );

            if ($this->nullableBool($hook, 'queue') ?? true) {
                $this->queues->dispatch(new SendMailJob($message, $this->nullableString($hook, 'mailer')));
                continue;
            }

            $this->mail->send($message, $this->nullableString($hook, 'mailer'));
        }
    }

    /** @return list<array<string, mixed>> */
    private function hooks(string $key, string $eventName): array
    {
        $hooks = $this->config->get("{$key}.{$eventName}", []);

        if (!is_array($hooks)) {
            return [];
        }

        $configured = [];

        foreach ($hooks as $hook) {
            if (!is_array($hook)) {
                continue;
            }

            $configured[] = $hook;
        }

        return $configured;
    }

    /** @param array<string, mixed> $hook */
    private function enabled(array $hook): bool
    {
        $enabled = $hook['enabled'] ?? true;

        return is_bool($enabled) ? $enabled : filter_var($enabled, FILTER_VALIDATE_BOOL);
    }

    /** @param array<string, mixed> $hook */
    private function string(array $hook, string $key): string
    {
        $value = $hook[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $hook */
    private function nullableString(array $hook, string $key): ?string
    {
        $value = $hook[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $hook */
    private function nullableBool(array $hook, string $key): ?bool
    {
        $value = $hook[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $hook
     * @return array<string, mixed>
     */
    private function array(array $hook, string $key): array
    {
        $value = $hook[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    private function nullableTemplate(?string $value, mixed $event): ?string
    {
        return $value === null ? null : $this->template($value, $event);
    }

    private function template(string $value, mixed $event): string
    {
        if (!is_object($event)) {
            return $value;
        }

        foreach (get_object_vars($event) as $key => $property) {
            if (is_scalar($property) || $property === null) {
                $value = str_replace('{' . $key . '}', (string) $property, $value);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function templateArray(array $values, mixed $event): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->template($value, $event);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function stringArray(array $values, mixed $event): array
    {
        $strings = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $strings[$key] = $this->template($value, $event);
            }
        }

        return $strings;
    }
}
