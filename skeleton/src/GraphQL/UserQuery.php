<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Models\User;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Query;

class UserQuery
{
    public function __construct(private readonly User $users) {}

    #[Query(name: 'user', type: 'User', description: 'Find a user by ID.')]
    #[Arg(name: 'id', type: 'ID!')]
    public function user(mixed $root, array $args): ?array
    {
        return $this->users->find((string) $args['id']);
    }

    #[Query(name: 'users', type: '[User!]!', description: 'List recent users.')]
    #[Arg(name: 'limit', type: 'Int', defaultValue: 25)]
    public function users(mixed $root, array $args): array
    {
        return $this->users->latest((int) ($args['limit'] ?? 25));
    }
}
