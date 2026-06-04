<?php

declare(strict_types=1);

namespace Fulcrum\Notifications\Transports;

use Fulcrum\Notifications\Notification;
use Fulcrum\Notifications\NotificationTransport;

class LogNotificationTransport implements NotificationTransport
{
    public function __construct(private readonly string $path) {}

    public function send(Notification $notification): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->path, json_encode([
            'to' => $notification->to,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
            'headers' => $notification->headers,
            'sent_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND);
    }
}
