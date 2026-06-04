<?php

declare(strict_types=1);

namespace Fulcrum\Mail;

class Message
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $html = null,
        public readonly ?string $from = null,
    ) {}

    public function withHeader(string $name, string $value): self
    {
        $message = clone $this;
        $message->headers[$name] = $value;

        return $message;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
