<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Marks a class as a GraphQL Object Type.
 *
 * Usage:
 *   #[ObjectType(name: 'User', description: 'An application user')]
 *   class UserType { ... }
 *
 * If `name` is omitted the compiler uses the short class name.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ObjectType
{
    public function __construct(
        public readonly string $name        = '',
        public readonly string $description = '',
    ) {}
}
