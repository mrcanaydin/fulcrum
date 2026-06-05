<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Models\User;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Query;

class UserQuery
{
    #[Query(name: 'user', type: 'User', description: 'Find a user by ID.')]
    #[Arg(name: 'id', type: 'ID!')]
    public function user(mixed $root, array $args): ?array
    {
        return User::find((string) $args['id'])?->toArray();
    }

    #[Query(name: 'users', type: 'UserConnection!', description: 'List users using forward cursor pagination.')]
    #[Arg(name: 'first', type: 'Int', defaultValue: 25)]
    #[Arg(name: 'after', type: 'String')]
    public function users(mixed $root, array $args): array
    {
        return User::query()
            ->cursorPaginate(
                first: (int) ($args['first'] ?? 25),
                after: isset($args['after']) ? (string) $args['after'] : null,
            )
            ->toArray();
    }
}
