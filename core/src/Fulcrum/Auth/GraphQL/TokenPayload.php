<?php

declare(strict_types=1);

namespace Fulcrum\Auth\GraphQL;

use Fulcrum\GraphQL\Attributes\ObjectType;
use Fulcrum\GraphQL\Attributes\Field;

#[ObjectType(name: 'TokenPayload', description: 'The result of a token creation.')]
class TokenPayload
{
    /** @param list<string> $abilities */
    public function __construct(
        #[Field(type: 'String!')]
        public string $accessToken,
        
        #[Field(type: 'String!')]
        public string $tokenType,
        
        #[Field(type: '[String!]!')]
        public array $abilities,
    ) {}
}
