<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

final class ForbiddenException extends ClientException
{
    public function __construct(string $message = 'Forbidden.')
    {
        parent::__construct($message, 'FORBIDDEN');
    }
}
