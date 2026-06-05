<?php

declare(strict_types=1);

namespace Fulcrum\Pagination;

use Fulcrum\GraphQL\Exceptions\ClientException;

final class InvalidCursorException extends ClientException
{
    public function __construct(string $message = 'Invalid pagination cursor.')
    {
        parent::__construct($message, 'INVALID_CURSOR');
    }
}
