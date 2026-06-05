<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

class ClientException extends RuntimeException implements ClientAware, ProvidesExtensions
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function getExtensions(): array
    {
        return ['code' => $this->errorCode] + $this->details;
    }

    /** @return array{message: string, extensions: array<string, mixed>} */
    public function toGraphQLError(): array
    {
        return [
            'message' => $this->getMessage(),
            'extensions' => $this->getExtensions(),
        ];
    }
}
