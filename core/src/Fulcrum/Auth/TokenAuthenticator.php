<?php

declare(strict_types=1);

namespace Fulcrum\Auth;

use Fulcrum\Routing\Request;
use Fulcrum\Auth\Models\PersonalAccessToken;
use Fulcrum\Database\DatabaseManager;

/**
 * Authenticates incoming HTTP Requests via Bearer token.
 */
class TokenAuthenticator
{
    public function __construct(
        protected PersonalAccessToken $tokens,
        protected DatabaseManager $db
    ) {}

    /**
     * Authenticate the request. Returns the authenticated User representation, or null.
     */
    /** @return array<string, mixed>|null */
    public function authenticate(Request $request): ?array
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $tokenStr = substr($header, 7);
        $parts    = explode('|', $tokenStr, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$tokenId, $plainTextToken] = $parts;

        $tokenRecord = $this->tokens->find($tokenId);

        if (!$tokenRecord) {
            return null;
        }

        // Validate hash
        $expectedHash = $tokenRecord['token'] ?? null;
        $actualHash   = hash('sha256', $plainTextToken);

        if (!is_string($expectedHash) || !hash_equals($expectedHash, $actualHash)) {
            return null;
        }

        // Check expiration
        $expiresAt = $tokenRecord['expires_at'] ?? null;
        if (is_string($expiresAt) && $expiresAt !== '' && strtotime($expiresAt) < time()) {
            return null;
        }

        // Touch last used at
        $this->tokens->touch($tokenId);

        // Fetch the user (the 'tokenable')
        $tokenableType = $tokenRecord['tokenable_type'] ?? null;
        $tokenableId = $tokenRecord['tokenable_id'] ?? null;
        if (
            !is_string($tokenableType)
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tokenableType) !== 1
            || !is_scalar($tokenableId)
        ) {
            return null;
        }

        $user = $this->db->table($tokenableType)
            ->where('id', $tokenableId)
            ->first();

        // If user was deleted but token remained
        if (!$user) {
            return null;
        }

        if (!empty($user['banned_at'])) {
            return null;
        }

        if (isset($user['id'])) {
            $user['id'] = (string) $user['id'];
        }

        // Inject the current token and abilities into the user so we can check it later
        $encodedAbilities = $tokenRecord['abilities'] ?? null;
        $abilities = is_string($encodedAbilities) ? json_decode($encodedAbilities, true) : [];
        $user['_token'] = $tokenRecord;
        $user['_token']['abilities'] = is_array($abilities) ? $abilities : [];

        return $user;
    }
}
