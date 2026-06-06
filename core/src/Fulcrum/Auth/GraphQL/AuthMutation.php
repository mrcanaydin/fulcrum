<?php

declare(strict_types=1);

namespace Fulcrum\Auth\GraphQL;

use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Authenticated;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Auth\TokenManager;

class AuthMutation
{
    public function __construct(
        protected TokenManager $tokenManager
    ) {}

    /** @param array<string, mixed> $args */
    #[Mutation(name: 'createToken', type: 'TokenPayload!', description: 'Create a new personal access token')]
    #[Arg(name: 'name', type: 'String!')]
    #[Arg(name: 'abilities', type: '[String!]', defaultValue: ['*'])]
    #[Authenticated]
    public function createToken(mixed $root, array $args, RequestContext $context): TokenPayload
    {
        [$tokenableType, $tokenableId] = $this->tokenOwner($context);
        $name = $args['name'] ?? null;
        $abilities = $args['abilities'] ?? ['*'];

        if (!is_string($name) || trim($name) === '' || !is_array($abilities)) {
            throw new ClientException('Token input is invalid.', 'TOKEN_INPUT_INVALID');
        }
        $abilities = array_values(array_filter($abilities, 'is_string'));
        $this->ensureAbilitiesCanBeDelegated($abilities, $context);

        $result = $this->tokenManager->createToken(
            $tokenableType,
            $tokenableId,
            trim($name),
            $abilities,
        );

        return new TokenPayload(
            $result['accessToken'],
            $result['tokenType'],
            $result['abilities']
        );
    }

    /** @param array<string, mixed> $args */
    #[Mutation(name: 'revokeToken', type: 'Boolean!', description: 'Revoke a specific token by its ID')]
    #[Arg(name: 'tokenId', type: 'ID!')]
    #[Authenticated]
    public function revokeToken(mixed $root, array $args, RequestContext $context): bool
    {
        [$tokenableType, $tokenableId] = $this->tokenOwner($context);
        $tokenId = $args['tokenId'] ?? null;

        return is_scalar($tokenId)
            && $this->tokenManager->revokeTokenForUser((string) $tokenId, $tokenableType, $tokenableId);
    }

    /** @param array<string, mixed> $args */
    #[Mutation(name: 'revokeAllTokens', type: 'Boolean!', description: 'Revoke all tokens for the current user')]
    #[Authenticated]
    public function revokeAllTokens(mixed $root, array $args, RequestContext $context): bool
    {
        [$tokenableType, $tokenableId] = $this->tokenOwner($context);

        return $this->tokenManager->revokeAllTokens($tokenableType, $tokenableId);
    }

    /** @return array{string, string} */
    private function tokenOwner(RequestContext $context): array
    {
        $user = $context->user();
        $tokenableType = is_array($user) && is_string($user['_table'] ?? null) ? $user['_table'] : 'users';
        $tokenableId = is_array($user) && is_scalar($user['id'] ?? null) ? (string) $user['id'] : '';

        if ($tokenableId === '') {
            throw new \RuntimeException('Authenticated user has no ID.');
        }

        return [$tokenableType, $tokenableId];
    }

    /**
     * @param list<string> $abilities
     */
    private function ensureAbilitiesCanBeDelegated(array $abilities, RequestContext $context): void
    {
        $user = $context->user();
        $token = is_array($user) && is_array($user['_token'] ?? null) ? $user['_token'] : [];
        $currentAbilities = is_array($token['abilities'] ?? null)
            ? array_values(array_filter($token['abilities'], 'is_string'))
            : [];

        if (in_array('*', $currentAbilities, true)) {
            return;
        }

        foreach ($abilities as $ability) {
            if ($ability === '*' || !in_array($ability, $currentAbilities, true)) {
                throw new ClientException('Token abilities cannot exceed the current token.', 'TOKEN_ABILITIES_FORBIDDEN');
            }
        }
    }
}
