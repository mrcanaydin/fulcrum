<?php

declare(strict_types=1);

namespace Fulcrum\Validation;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given input was invalid.');
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array{message: string, extensions: array{category: string, validation: array<string, list<string>>}} */
    public function toGraphQLError(): array
    {
        return [
            'message' => $this->getMessage(),
            'extensions' => [
                'category' => 'validation',
                'validation' => $this->errors,
            ],
        ];
    }
}
