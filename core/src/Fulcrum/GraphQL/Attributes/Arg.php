<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Declares a GraphQL argument on a Query or Mutation field.
 *
 * Repeatable — stack multiple #[Arg] on the same method.
 *
 * Usage:
 *   #[Arg(name: 'id',    type: 'ID!')]
 *   #[Arg(name: 'limit', type: 'Int', defaultValue: 20)]
 *   #[Query(name: 'user', type: 'User')]
 *   public function user($root, array $args, RequestContext $ctx): array { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Arg
{
    public function __construct(
        public readonly string $name         = '',
        public readonly string $type         = 'String',
        public readonly string $description  = '',
        public readonly mixed  $defaultValue = null,
    ) {}
}
