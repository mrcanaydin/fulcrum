<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Marks a method as a GraphQL Subscription field (stub — Phase 5+).
 *
 * Usage:
 *   #[Subscription(name: 'messageAdded', type: 'Message!')]
 *   public function messageAdded($root, array $args, RequestContext $ctx): mixed { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Subscription
{
    public function __construct(
        public readonly string $name        = '',
        public readonly string $type        = '',
        public readonly string $description = '',
    ) {}
}
