<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

final class PersistedQueryException extends ClientException
{
    public function __construct(string $message, string $code)
    {
        parent::__construct($message, $code);
    }
}
