<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Pagination;

use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'PageInfo', description: 'Metadata for a cursor-paginated connection.')]
final class PageInfoType
{
    #[Field(type: 'Boolean!')]
    public bool $hasNextPage;

    #[Field(type: 'Boolean!')]
    public bool $hasPreviousPage;

    #[Field(type: 'String')]
    public ?string $startCursor = null;

    #[Field(type: 'String')]
    public ?string $endCursor = null;
}
