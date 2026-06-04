<?php

declare(strict_types=1);

namespace Fulcrum\Mail\Transports;

use Fulcrum\Mail\MailTransport;
use Fulcrum\Mail\Message;

class LogTransport implements MailTransport
{
    public function __construct(private readonly string $path) {}

    public function send(Message $message): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->path, json_encode([
            'to' => $message->to,
            'from' => $message->from,
            'subject' => $message->subject,
            'text' => $message->text,
            'html' => $message->html,
            'headers' => $message->headers(),
            'sent_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND);
    }
}
