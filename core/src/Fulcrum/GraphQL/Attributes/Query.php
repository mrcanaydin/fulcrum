<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Marks a method as a GraphQL Query field.
 *
 * Usage:
 *   #[Query(name: 'users', type: '[User!]!', description: 'List all users')]
 *   public function users($root, array $args, RequestContext $ctx): array { ... }
 *
 * The annotated class must be registered with GraphQLServiceProvider.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Query
{
    public function __construct(
        public readonly string $name        = '',
        public readonly string $type        = '',
        public readonly string $description = '',
        public readonly ?string $deprecationReason = null,
    ) {}
}
