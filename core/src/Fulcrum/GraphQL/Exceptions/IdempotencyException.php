<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

final class IdempotencyException extends ClientException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message, string $code = 'IDEMPOTENCY_KEY_INVALID', array $details = [])
    {
        parent::__construct($message, $code, $details);
    }
}
