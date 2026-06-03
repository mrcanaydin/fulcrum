<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

/**
 * Intermediate representation of a single GraphQL argument.
 */
final class ArgDef
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description  = '',
        public readonly mixed  $defaultValue = null,
        public readonly bool   $hasDefault   = false,
    ) {}
}
