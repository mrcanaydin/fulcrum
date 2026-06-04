<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Events\UserCreated;
use App\Models\User;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\Validation\Validator;

class UserMutation
{
    public function __construct(
        private readonly Validator $validator,
        private readonly EventDispatcher $events,
    ) {}

    #[Mutation(name: 'createUser', type: 'User!', description: 'Create an example user.')]
    #[Arg(name: 'name', type: 'String!')]
    #[Arg(name: 'email', type: 'String!')]
    public function createUser(mixed $root, array $args): array
    {
        $input = $this->validator->validate(
            $args,
            [
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255',
            ],
            [
                'name' => ['trim', 'strip_tags'],
                'email' => ['email', 'lower'],
            ]
        );

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ])->toArray();

        $this->events->dispatch(new UserCreated((string) $user['id'], (string) $user['email']));

        return $user;
    }
}
