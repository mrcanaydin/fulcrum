<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Models\User;
use Fulcrum\Auth\GraphQL\TokenPayload;
use Fulcrum\Auth\TokenManager;
use Fulcrum\Cache\CacheManager;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Authenticated;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Validation\Validator;
use Throwable;

class AuthMutation
{
    public function __construct(
        private readonly Validator $validator,
        private readonly TokenManager $tokens,
        private readonly CacheManager $cache,
        private readonly Config $config,
    ) {}

    /** @param array<string, mixed> $args */
    #[Mutation(name: 'login', type: 'TokenPayload!', description: 'Exchange valid user credentials for an access token.')]
    #[Arg(name: 'email', type: 'String!')]
    #[Arg(name: 'password', type: 'String!')]
    #[Arg(name: 'deviceName', type: 'String', defaultValue: 'api-client')]
    public function login(mixed $root, array $args, RequestContext $context): TokenPayload
    {
        $input = $this->validator->validate(
            $args,
            [
                'email' => 'required|email|max:255',
                'password' => 'required|string|max:4096',
                'deviceName' => 'nullable|string|min:2|max:255',
            ],
            [
                'email' => ['email', 'lower'],
                'deviceName' => ['trim', 'strip_tags'],
            ]
        );
        $key = $this->loginThrottleKey((string) $input['email'], $context);
        $this->throttleLogin($key);

        try {
            $user = User::query()->where('email', $input['email'])->first();
            $hash = $user instanceof User ? $user->getAttribute('password_hash') : null;

            if (
                !$user instanceof User
                || !is_string($hash)
                || !password_verify((string) $input['password'], $hash)
                || $user->getAttribute('banned_at') !== null
                || ($this->requiresVerifiedEmail() && $user->getAttribute('email_verified_at') === null)
            ) {
                throw $this->invalidCredentials();
            }

            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                User::query()->where('id', (string) $user->getAttribute('id'))->update([
                    'password_hash' => password_hash((string) $input['password'], PASSWORD_DEFAULT),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            $this->cache->store()->forget($key);
            $result = $this->tokens->createToken(
                'users',
                (string) $user->getAttribute('id'),
                (string) ($input['deviceName'] ?? 'api-client'),
                $this->abilities(),
                $this->tokenTtl(),
            );

            return new TokenPayload($result['accessToken'], $result['tokenType'], $result['abilities']);
        } catch (ClientException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->invalidCredentials();
        }
    }

    /** @param array<string, mixed> $args */
    #[Mutation(name: 'logout', type: 'Boolean!', description: 'Revoke the access token used for this request.')]
    #[Authenticated]
    public function logout(mixed $root, array $args, RequestContext $context): bool
    {
        $user = $context->user();
        $token = is_array($user) ? ($user['_token'] ?? null) : null;
        $tokenId = is_array($token) ? ($token['id'] ?? null) : null;
        $userId = is_array($user) ? ($user['id'] ?? null) : null;

        return is_scalar($tokenId)
            && is_scalar($userId)
            && $this->tokens->revokeTokenForUser((string) $tokenId, 'users', (string) $userId);
    }

    private function throttleLogin(string $key): void
    {
        $maxAttempts = max(1, (int) $this->config->get('auth.login_rate_limit.max_attempts', 5));
        $decaySeconds = max(1, (int) $this->config->get('auth.login_rate_limit.decay_seconds', 900));

        try {
            $attempts = $this->cache->store()->increment($key, 1, $decaySeconds);
        } catch (Throwable) {
            throw $this->invalidCredentials();
        }

        if ($attempts > $maxAttempts) {
            throw new ClientException(
                'Unable to authenticate with the provided credentials.',
                'RATE_LIMITED',
                ['retryAfter' => $decaySeconds],
            );
        }
    }

    private function loginThrottleKey(string $email, RequestContext $context): string
    {
        return 'login:' . hash('sha256', $email . '|' . $context->request()->clientIp());
    }

    /** @return list<string> */
    private function abilities(): array
    {
        $abilities = $this->config->get('auth.token_abilities', []);

        return is_array($abilities) ? array_values(array_filter($abilities, 'is_string')) : [];
    }

    private function tokenTtl(): int
    {
        return max(60, (int) $this->config->get('auth.token_ttl_seconds', 2592000));
    }

    private function requiresVerifiedEmail(): bool
    {
        return (bool) $this->config->get('auth.require_verified_email', false);
    }

    private function invalidCredentials(): ClientException
    {
        return new ClientException(
            'Unable to authenticate with the provided credentials.',
            'INVALID_CREDENTIALS',
        );
    }
}
