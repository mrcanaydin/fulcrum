<?php

declare(strict_types=1);

namespace Fulcrum\Notifications\Transports;

use Fulcrum\Notifications\Notification;
use Fulcrum\Notifications\NotificationTransport;
use RuntimeException;

class WebhookNotificationTransport implements NotificationTransport
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token = null,
        private readonly int $timeout = 10,
    ) {}

    public function send(Notification $notification): void
    {
        if ($this->url === '') {
            throw new RuntimeException('Webhook notification transport requires a URL.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        foreach ($notification->headers as $name => $value) {
            $headers[] = $this->header($name) . ': ' . $this->header($value);
        }

        $body = json_encode([
            'to' => $notification->to,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
        ], JSON_THROW_ON_ERROR);

        $response = file_get_contents($this->url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => max(1, $this->timeout),
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            throw new RuntimeException('Webhook notification request failed.');
        }
    }

    private function header(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
