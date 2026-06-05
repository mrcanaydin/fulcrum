<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Subscriptions;

use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ForbiddenException;
use Fulcrum\GraphQL\Exceptions\UnauthenticatedException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Container\Contracts\ContainerInterface;

class SubscriptionAuthorizer
{
    public function __construct(
        private readonly Config $config,
        private readonly ContainerInterface $container,
    ) {}

    public function authorize(string $topic, RequestContext $context): void
    {
        $topics = $this->config->get('subscriptions.topics', []);
        $settings = is_array($topics) ? ($topics[$topic] ?? null) : null;

        if (!is_array($settings)) {
            throw new ForbiddenException('Subscription topic is not allowed.');
        }

        /** @var array<string, mixed> $settings */
        if (($settings['authenticated'] ?? false) === true && !$context->isAuth()) {
            throw new UnauthenticatedException();
        }

        $required = $settings['abilities'] ?? [];
        if (is_array($required) && $required !== []) {
            $user = $context->user();
            $token = is_array($user) ? ($user['_token'] ?? null) : null;
            $abilities = is_array($token) && is_array($token['abilities'] ?? null) ? $token['abilities'] : [];

            foreach ($required as $ability) {
                if (is_string($ability) && !in_array('*', $abilities, true) && !in_array($ability, $abilities, true)) {
                    throw new ForbiddenException('Subscription is not authorized.');
                }
            }
        }

        $hook = $settings['authorizer'] ?? null;
        if (is_string($hook) && $hook !== '') {
            $authorizer = $this->container->get($hook);

            if (!$authorizer instanceof SubscriptionAuthorizationHook || !$authorizer->authorize($topic, $context)) {
                throw new ForbiddenException('Subscription is not authorized.');
            }
        }
    }
}
