<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

class Notification
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $to,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly array $headers = [],
        public readonly ?string $locale = null,
    ) {}
}
