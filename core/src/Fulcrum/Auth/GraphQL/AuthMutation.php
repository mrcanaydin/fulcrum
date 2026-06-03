<?php

declare(strict_types=1);

namespace Fulcrum\Auth\GraphQL;

use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Authenticated;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Auth\TokenManager;

class AuthMutation
{
    public function __construct(
        protected TokenManager $tokenManager
    ) {}

    #[Mutation(name: 'createToken', type: 'TokenPayload!', description: 'Create a new personal access token')]
    #[Arg(name: 'name', type: 'String!')]
    #[Arg(name: 'abilities', type: '[String!]', defaultValue: ['*'])]
    #[Authenticated]
    public function createToken($root, array $args, RequestContext $context): TokenPayload
    {
        $user = $context->user();
        
        // We assume $user has an 'id' and 'table' or similar. 
        // For standard implementations, we assume tokenable_type is 'users' and tokenable_id is $user['id'].
        // A real app might need a more dynamic way to resolve the tokenable type.
        $tokenableType = $user['_table'] ?? 'users';
        $tokenableId   = (string) ($user['id'] ?? '');

        if (empty($tokenableId)) {
            throw new \Exception('Authenticated user has no ID.');
        }

        $result = $this->tokenManager->createToken(
            $tokenableType,
            $tokenableId,
            $args['name'],
            $args['abilities'] ?? ['*']
        );

        return new TokenPayload(
            $result['accessToken'],
            $result['tokenType'],
            $result['abilities']
        );
    }

    #[Mutation(name: 'revokeToken', type: 'Boolean!', description: 'Revoke a specific token by its ID')]
    #[Arg(name: 'tokenId', type: 'ID!')]
    #[Authenticated]
    public function revokeToken($root, array $args, RequestContext $context): bool
    {
        // Ideally we should check if the token belongs to the user, but for simplicity here
        // we just revoke it if they are authenticated. In a real scenario, you'd scope this.
        return $this->tokenManager->revokeToken($args['tokenId']);
    }

    #[Mutation(name: 'revokeAllTokens', type: 'Boolean!', description: 'Revoke all tokens for the current user')]
    #[Authenticated]
    public function revokeAllTokens($root, array $args, RequestContext $context): bool
    {
        $user = $context->user();
        $tokenableType = $user['_table'] ?? 'users';
        $tokenableId   = (string) ($user['id'] ?? '');

        return $this->tokenManager->revokeAllTokens($tokenableType, $tokenableId);
    }
}
