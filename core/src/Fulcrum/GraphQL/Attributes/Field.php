<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Declares a method or property as a GraphQL field.
 *
 * Usage:
 *   #[Field(type: 'String!', description: 'The user's email address')]
 *   public function email(): string { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
final class Field
{
    public function __construct(
        /** GraphQL type string, e.g. "String!", "ID", "[User!]!" */
        public readonly string $type        = '',
        public readonly string $name        = '',
        public readonly string $description = '',
        public readonly bool   $nullable    = true,
    ) {}
}
