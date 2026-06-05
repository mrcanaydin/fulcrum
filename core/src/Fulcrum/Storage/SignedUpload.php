<?php

declare(strict_types=1);

namespace Fulcrum\Storage;

use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'SignedUpload', description: 'Direct object-storage upload instructions.')]
class SignedUpload
{
    /** @param array<string, string> $headers */
    public function __construct(
        #[Field(type: 'String!')]
        public readonly string $url,
        #[Field(type: 'String!')]
        public readonly string $method,
        #[Field(type: 'JSON!')]
        public readonly array $headers,
        #[Field(type: 'String!')]
        public readonly string $path,
        #[Field(type: 'Int!')]
        public readonly int $expiresAt,
    ) {}
}
