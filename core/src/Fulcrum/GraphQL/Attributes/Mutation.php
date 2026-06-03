<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Marks a method as a GraphQL Mutation field.
 *
 * Usage:
 *   #[Mutation(name: 'createUser', type: 'User!')]
 *   public function createUser($root, array $args, RequestContext $ctx): array { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Mutation
{
    public function __construct(
        public readonly string $name        = '',
        public readonly string $type        = '',
        public readonly string $description = '',
    ) {}
}
