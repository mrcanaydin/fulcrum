<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

final class UnauthenticatedException extends ClientException
{
    public function __construct(string $message = 'Unauthenticated.')
    {
        parent::__construct($message, 'UNAUTHENTICATED');
    }
}
