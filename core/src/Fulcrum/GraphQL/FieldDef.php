<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

/**
 * Intermediate representation of a single GraphQL field (on a type, query root,
 * or mutation root).
 *
 * Produced by AttributeCompiler; consumed by SchemaCompiler.
 */
final class FieldDef
{
    /**
     * @param string            $name          GraphQL field name
     * @param string            $type          GraphQL type string, e.g. "String!", "[User!]!"
     * @param string            $methodName    PHP method that acts as the resolver
     * @param array<int, ArgDef> $args         Declared arguments
     * @param string            $description
     * @param bool              $authenticated Whether #[Authenticated] was present
     * @param array<int, string> $requiredAbilities Abilities required to access this field
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $methodName,
        public readonly array  $args              = [],
        public readonly string $description       = '',
        public readonly bool   $authenticated     = false,
        public readonly array  $requiredAbilities = [],
    ) {}
}
