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
        $expectedHash = $tokenRecord['token'];
        $actualHash   = hash('sha256', $plainTextToken);

        if (!hash_equals($expectedHash, $actualHash)) {
            return null;
        }

        // Check expiration
        if (!empty($tokenRecord['expires_at']) && strtotime($tokenRecord['expires_at']) < time()) {
            return null;
        }

        // Touch last used at
        $this->tokens->touch($tokenId);

        // Fetch the user (the 'tokenable')
        $user = $this->db->table($tokenRecord['tokenable_type'])
            ->where('id', $tokenRecord['tokenable_id'])
            ->first();

        // If user was deleted but token remained
        if (!$user) {
            return null;
        }

        if (isset($user['id'])) {
            $user['id'] = (string) $user['id'];
        }

        // Inject the current token and abilities into the user so we can check it later
        $user['_token'] = $tokenRecord;
        $user['_token']['abilities'] = json_decode($tokenRecord['abilities'] ?? '[]', true);

        return $user;
    }
}
