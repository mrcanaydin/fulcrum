<?php

declare(strict_types=1);

namespace App\GraphQL;

use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'UserConnection')]
class UserConnection
{
    #[Field(type: '[User!]!')]
    public array $nodes;

    #[Field(type: '[UserEdge!]!')]
    public array $edges;

    #[Field(type: 'PageInfo!')]
    public array $pageInfo;
}
