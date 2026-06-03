<?php

declare(strict_types=1);

namespace Fulcrum\Auth\GraphQL;

use Fulcrum\GraphQL\Attributes\Query;
use Fulcrum\GraphQL\Attributes\Authenticated;
use Fulcrum\GraphQL\RequestContext;

class AuthQuery
{
    /**
     * Note: We cannot type 'User' here universally because the framework doesn't
     * define the User ObjectType, the application does. We will return mixed and
     * let the schema resolve it if the application defines a `User` type.
     * Actually, if we define the query here, we need to know the type name.
     * The simplest is to assume the skeleton provides a `User` type, so we use `User!`.
     */
    #[Query(name: 'me', type: 'User!', description: 'Returns the currently authenticated user')]
    #[Authenticated]
    public function me($root, array $args, RequestContext $context): mixed
    {
        return $context->user();
    }
}
