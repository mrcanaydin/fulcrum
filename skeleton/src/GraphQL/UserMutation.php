<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Events\UserCreated;
use App\Jobs\SendEmailVerificationJob;
use App\Models\User;
use Fulcrum\Auth\Attributes\RequiresAbility;
use Fulcrum\Cache\CacheManager;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\GraphQL\Exceptions\NotFoundException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Validation\Validator;
use Throwable;

class UserMutation
{
    public function __construct(
        private readonly Validator $validator,
        private readonly EventDispatcher $events,
        private readonly QueueManager $queues,
        private readonly Config $config,
        private readonly DatabaseManager $db,
        private readonly CacheManager $cache,
    ) {}

    #[Mutation(name: 'createUser', type: 'User!', description: 'Create an example user.')]
    #[Arg(name: 'name', type: 'String!')]
    #[Arg(name: 'email', type: 'String!')]
    #[Arg(name: 'avatar', type: 'String')]
    #[Arg(name: 'gender', type: 'String')]
    #[Arg(name: 'birthday', type: 'String')]
    public function createUser(mixed $root, array $args, RequestContext $context): array
    {
        $input = $this->validator->validate(
            $args,
            [
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255',
                'avatar' => 'nullable|url|max:2048',
                'gender' => 'nullable|string|in:male,female,non_binary,other,prefer_not_to_say|max:32',
                'birthday' => 'nullable|date_format:Y-m-d',
            ],
            [
                'name' => ['trim', 'strip_tags'],
                'email' => ['email', 'lower'],
                'avatar' => ['url'],
                'gender' => ['trim', 'strip_tags', 'lower'],
                'birthday' => ['trim', 'strip_tags'],
            ]
        );

        try {
            return $this->db->transaction(function () use ($input, $context): array {
                if (User::query()->where('email', $input['email'])->first() instanceof User) {
                    throw new ClientException(
                        'A user with this email already exists.',
                        'USER_EMAIL_ALREADY_EXISTS',
                    );
                }

                $verificationEnabled = (bool) $this->config->get('users.email_verification.enabled', false);
                $plainVerificationToken = $verificationEnabled ? bin2hex(random_bytes(32)) : null;
                $verificationExpiresAt = $verificationEnabled ? $this->verificationExpiresAt() : null;
                $now = gmdate('Y-m-d H:i:s');
                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'locale' => $context->locale(),
                    'avatar' => $input['avatar'] ?? null,
                    'gender' => $input['gender'] ?? null,
                    'birthday' => $input['birthday'] ?? null,
                    'email_verified_at' => $verificationEnabled ? null : $now,
                    'email_verification_token' => is_string($plainVerificationToken)
                        ? hash('sha256', $plainVerificationToken)
                        : null,
                    'email_verification_expires_at' => $verificationExpiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                $this->events->dispatchAfterCommit(new UserCreated(
                    (string) $user['id'],
                    (string) $user['email'],
                    $context->locale(),
                ));

                if (is_string($plainVerificationToken)) {
                    $this->queues->dispatchAfterCommit(
                        new SendEmailVerificationJob((string) $user['email'], $plainVerificationToken, $context->locale())
                    );
                }

                return $user;
            });
        } catch (ClientException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ClientException('Unable to create user.', 'USER_CREATE_FAILED');
        }
    }

    #[Mutation(name: 'sendUserEmailVerification', type: 'Boolean!', description: 'Queue a user email verification message when enabled.')]
    #[Arg(name: 'email', type: 'String!')]
    public function sendUserEmailVerification(mixed $root, array $args, RequestContext $context): bool
    {
        $input = $this->validator->validate(
            $args,
            ['email' => 'required|email|max:255'],
            ['email' => ['email', 'lower']]
        );
        $this->throttleEmailVerification((string) $input['email']);

        if (!(bool) $this->config->get('users.email_verification.enabled', false)) {
            return true;
        }

        try {
            $this->db->transaction(function () use ($input, $context): void {
                $user = User::query()->where('email', $input['email'])->first();

                if (!$user instanceof User || $user->getAttribute('email_verified_at') !== null) {
                    return;
                }

                $plainToken = bin2hex(random_bytes(32));
                User::query()->where('id', (string) $user->getAttribute('id'))->update([
                    'email_verification_token' => hash('sha256', $plainToken),
                    'email_verification_expires_at' => $this->verificationExpiresAt(),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);

                $this->queues->dispatchAfterCommit(
                    new SendEmailVerificationJob((string) $user->getAttribute('email'), $plainToken, $context->locale())
                );
            });
        } catch (Throwable) {
            throw new ClientException(
                'Unable to process email verification request.',
                'EMAIL_VERIFICATION_REQUEST_FAILED',
            );
        }

        return true;
    }

    #[Mutation(name: 'verifyUserEmail', type: 'Boolean!', description: 'Verify a user email by token when verification is enabled.')]
    #[Arg(name: 'token', type: 'String!')]
    public function verifyUserEmail(mixed $root, array $args): bool
    {
        if (!(bool) $this->config->get('users.email_verification.enabled', false)) {
            return false;
        }

        $input = $this->validator->validate(
            $args,
            ['token' => 'required|string|min:32|max:128'],
            ['token' => ['trim', 'strip_tags']]
        );

        try {
            $user = User::query()
                ->where('email_verification_token', hash('sha256', (string) $input['token']))
                ->first();

            if (!$user instanceof User) {
                return false;
            }

            $expiresAt = $user->getAttribute('email_verification_expires_at');
            if (is_string($expiresAt) && strtotime($expiresAt) < time()) {
                return false;
            }

            User::query()->where('id', (string) $user->getAttribute('id'))->update([
                'email_verified_at' => gmdate('Y-m-d H:i:s'),
                'email_verification_token' => null,
                'email_verification_expires_at' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            throw new ClientException('Unable to verify email.', 'EMAIL_VERIFICATION_FAILED');
        }

        return true;
    }

    #[Mutation(name: 'banUser', type: 'User', description: 'Ban a user with a reason.')]
    #[RequiresAbility('users:manage')]
    #[Arg(name: 'id', type: 'ID!')]
    #[Arg(name: 'reason', type: 'String!')]
    public function banUser(mixed $root, array $args): ?array
    {
        $input = $this->validator->validate(
            $args,
            ['id' => 'required|string', 'reason' => 'required|string|min:2|max:1000'],
            ['reason' => ['trim', 'strip_tags']]
        );

        try {
            $user = User::find((string) $input['id']);
            if (!$user instanceof User) {
                throw new NotFoundException('User not found.');
            }

            User::query()->where('id', (string) $input['id'])->update([
                'banned_at' => gmdate('Y-m-d H:i:s'),
                'ban_reason' => $input['reason'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            return User::find((string) $input['id'])?->toArray();
        } catch (ClientException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ClientException('Unable to ban user.', 'USER_BAN_FAILED');
        }
    }

    #[Mutation(name: 'unbanUser', type: 'User', description: 'Remove a user ban.')]
    #[RequiresAbility('users:manage')]
    #[Arg(name: 'id', type: 'ID!')]
    public function unbanUser(mixed $root, array $args): ?array
    {
        $input = $this->validator->validate($args, ['id' => 'required|string']);

        try {
            $user = User::find((string) $input['id']);
            if (!$user instanceof User) {
                throw new NotFoundException('User not found.');
            }

            User::query()->where('id', (string) $input['id'])->update([
                'banned_at' => null,
                'ban_reason' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            return User::find((string) $input['id'])?->toArray();
        } catch (ClientException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ClientException('Unable to unban user.', 'USER_UNBAN_FAILED');
        }
    }

    private function verificationExpiresAt(): string
    {
        $minutes = max(1, (int) $this->config->get('users.email_verification.expires_minutes', 60));

        return gmdate('Y-m-d H:i:s', time() + (60 * $minutes));
    }

    private function throttleEmailVerification(string $email): void
    {
        $maxAttempts = max(1, (int) $this->config->get('users.email_verification.rate_limit.max_attempts', 5));
        $decaySeconds = max(1, (int) $this->config->get('users.email_verification.rate_limit.decay_seconds', 3600));
        $key = 'user_email_verification:' . hash('sha256', $email);

        try {
            $attempts = $this->cache->store()->increment($key, 1, $decaySeconds);
        } catch (Throwable) {
            throw new ClientException(
                'Unable to process email verification request.',
                'EMAIL_VERIFICATION_REQUEST_FAILED',
            );
        }

        if ($attempts > $maxAttempts) {
            throw new ClientException(
                'Too many email verification requests. Please try again later.',
                'RATE_LIMITED',
                ['retryAfter' => $decaySeconds],
            );
        }
    }
}
