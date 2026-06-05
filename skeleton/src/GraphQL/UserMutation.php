<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Events\UserCreated;
use App\Jobs\SendEmailVerificationJob;
use App\Models\User;
use Fulcrum\Auth\Attributes\RequiresAbility;
use Fulcrum\Events\EventDispatcher;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Validation\Validator;

class UserMutation
{
    public function __construct(
        private readonly Validator $validator,
        private readonly EventDispatcher $events,
        private readonly QueueManager $queues,
        private readonly Config $config,
    ) {}

    #[Mutation(name: 'createUser', type: 'User!', description: 'Create an example user.', idempotent: true)]
    #[Arg(name: 'name', type: 'String!')]
    #[Arg(name: 'email', type: 'String!')]
    #[Arg(name: 'avatar', type: 'String')]
    #[Arg(name: 'gender', type: 'String')]
    #[Arg(name: 'birthday', type: 'String')]
    public function createUser(mixed $root, array $args): array
    {
        $input = $this->validator->validate(
            $args,
            [
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255',
                'avatar' => 'nullable|url|max:2048',
                'gender' => 'nullable|string|in:male,female,non_binary,other,prefer_not_to_say|max:32',
                'birthday' => 'nullable|string|max:10',
            ],
            [
                'name' => ['trim', 'strip_tags'],
                'email' => ['email', 'lower'],
                'avatar' => ['url'],
                'gender' => ['trim', 'strip_tags', 'lower'],
                'birthday' => ['trim', 'strip_tags'],
            ]
        );

        $verificationEnabled = (bool) $this->config->get('users.email_verification.enabled', false);
        $verificationToken = $verificationEnabled ? bin2hex(random_bytes(32)) : null;
        $verificationExpiresAt = $verificationEnabled
            ? gmdate('Y-m-d H:i:s', time() + (60 * (int) $this->config->get('users.email_verification.expires_minutes', 60)))
            : null;
        $now = gmdate('Y-m-d H:i:s');
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'avatar' => $input['avatar'] ?? null,
            'gender' => $input['gender'] ?? null,
            'birthday' => $input['birthday'] ?? null,
            'email_verified_at' => $verificationEnabled ? null : $now,
            'email_verification_token' => $verificationToken,
            'email_verification_expires_at' => $verificationExpiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        $this->events->dispatchAfterCommit(new UserCreated((string) $user['id'], (string) $user['email']));

        if ($verificationEnabled && is_string($verificationToken)) {
            $this->queues->dispatchAfterCommit(new SendEmailVerificationJob((string) $user['email'], $verificationToken));
        }

        return $user;
    }

    #[Mutation(name: 'sendUserEmailVerification', type: 'Boolean!', description: 'Queue a user email verification message when enabled.')]
    #[Arg(name: 'email', type: 'String!')]
    public function sendUserEmailVerification(mixed $root, array $args): bool
    {
        if (!(bool) $this->config->get('users.email_verification.enabled', false)) {
            return false;
        }

        $input = $this->validator->validate(
            $args,
            ['email' => 'required|email|max:255'],
            ['email' => ['email', 'lower']]
        );

        $user = User::query()->where('email', $input['email'])->first();

        if (!$user instanceof User || $user->getAttribute('email_verified_at') !== null) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (60 * (int) $this->config->get('users.email_verification.expires_minutes', 60)));

        User::query()->where('id', (string) $user->getAttribute('id'))->update([
            'email_verification_token' => $token,
            'email_verification_expires_at' => $expiresAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->queues->dispatch(new SendEmailVerificationJob((string) $user->getAttribute('email'), $token));

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

        $user = User::query()->where('email_verification_token', $input['token'])->first();

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

        User::query()->where('id', (string) $input['id'])->update([
            'banned_at' => gmdate('Y-m-d H:i:s'),
            'ban_reason' => $input['reason'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return User::find((string) $input['id'])?->toArray();
    }

    #[Mutation(name: 'unbanUser', type: 'User', description: 'Remove a user ban.')]
    #[RequiresAbility('users:manage')]
    #[Arg(name: 'id', type: 'ID!')]
    public function unbanUser(mixed $root, array $args): ?array
    {
        $input = $this->validator->validate($args, ['id' => 'required|string']);

        User::query()->where('id', (string) $input['id'])->update([
            'banned_at' => null,
            'ban_reason' => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return User::find((string) $input['id'])?->toArray();
    }
}
