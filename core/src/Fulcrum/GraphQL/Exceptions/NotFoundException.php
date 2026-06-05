<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

final class NotFoundException extends ClientException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message, 'NOT_FOUND');
    }
}
