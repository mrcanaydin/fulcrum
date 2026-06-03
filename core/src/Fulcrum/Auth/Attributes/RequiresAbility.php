<?php

declare(strict_types=1);

namespace Fulcrum\Auth\Attributes;

/**
 * Guards a GraphQL Query or Mutation — requires the authenticated user's token
 * to have the specified ability (or '*').
 *
 * Usage:
 *   #[RequiresAbility('read:posts')]
 *   public function posts($root, array $args, RequestContext $ctx): array { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RequiresAbility
{
    public function __construct(
        public readonly string $ability
    ) {}
}
