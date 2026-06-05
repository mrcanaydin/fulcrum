<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Subscriptions;

use Fulcrum\GraphQL\RequestContext;

interface SubscriptionAuthorizationHook
{
    public function authorize(string $topic, RequestContext $context): bool;
}
