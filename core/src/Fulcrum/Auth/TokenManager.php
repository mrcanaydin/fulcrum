<?php

declare(strict_types=1);

namespace Fulcrum\Auth;

use Fulcrum\Auth\Models\PersonalAccessToken;
use Fulcrum\Support\Str;

/**
 * Manages creation and revocation of tokens.
 */
class TokenManager
{
    public function __construct(
        protected PersonalAccessToken $tokens
    ) {}

    /**
     * @param string $tokenableType e.g., 'users'
     * @param string $tokenableId   e.g., '1'
     * @param string $name          e.g., 'mobile-app'
     * @param array<string> $abilities
     * @return array{accessToken: string, tokenType: string, abilities: array<string>}
     */
    public function createToken(string $tokenableType, string $tokenableId, string $name, array $abilities = ['*']): array
    {
        $plainTextToken = Str::random(40);
        $hashedToken    = hash('sha256', $plainTextToken);

        $id = $this->tokens->create([
            'tokenable_type' => $tokenableType,
            'tokenable_id'   => $tokenableId,
            'name'           => $name,
            'token'          => $hashedToken,
            'abilities'      => json_encode($abilities),
            'created_at'     => gmdate('Y-m-d H:i:s'),
            'updated_at'     => gmdate('Y-m-d H:i:s'),
        ]);

        return [
            'accessToken' => $id . '|' . $plainTextToken,
            'tokenType'   => 'Bearer',
            'abilities'   => $abilities,
        ];
    }

    public function revokeToken(string $tokenId): bool
    {
        return $this->tokens->delete($tokenId) > 0;
    }

    public function revokeAllTokens(string $tokenableType, string $tokenableId): bool
    {
        return $this->tokens->deleteAllForUser($tokenableType, $tokenableId) > 0;
    }
}
