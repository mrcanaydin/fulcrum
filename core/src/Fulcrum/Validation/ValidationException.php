<?php

declare(strict_types=1);

namespace Fulcrum\Validation;

use Fulcrum\GraphQL\Exceptions\ClientException;

class ValidationException extends ClientException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(
            'The given input was invalid.',
            'VALIDATION_FAILED',
            ['validation' => $errors],
        );
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
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
