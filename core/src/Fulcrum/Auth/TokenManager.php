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
     * @param list<string> $abilities
     * @return array{accessToken: string, tokenType: string, abilities: list<string>}
     */
    public function createToken(
        string $tokenableType,
        string $tokenableId,
        string $name,
        array $abilities = ['*'],
        ?int $expiresIn = null,
    ): array
    {
        $plainTextToken = Str::random(40);
        $hashedToken    = hash('sha256', $plainTextToken);
        $expiresAt = $expiresIn !== null
            ? gmdate('Y-m-d H:i:s', time() + max(60, $expiresIn))
            : null;

        $id = $this->tokens->create([
            'tokenable_type' => $tokenableType,
            'tokenable_id'   => $tokenableId,
            'name'           => $name,
            'token'          => $hashedToken,
            'abilities'      => json_encode($abilities),
            'expires_at'     => $expiresAt,
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

    public function revokeTokenForUser(string $tokenId, string $tokenableType, string $tokenableId): bool
    {
        return $this->tokens->deleteForUser($tokenId, $tokenableType, $tokenableId) > 0;
    }

    public function revokeAllTokens(string $tokenableType, string $tokenableId): bool
    {
        return $this->tokens->deleteAllForUser($tokenableType, $tokenableId) > 0;
    }
}
