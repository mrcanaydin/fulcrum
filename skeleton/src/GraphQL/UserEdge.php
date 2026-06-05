<?php

declare(strict_types=1);

namespace App\GraphQL;

use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'UserEdge')]
class UserEdge
{
    #[Field(type: 'String!')]
    public string $cursor;

    #[Field(type: 'User!')]
    public array $node;
}
