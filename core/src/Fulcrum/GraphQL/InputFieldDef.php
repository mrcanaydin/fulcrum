<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

final class InputFieldDef
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description = '',
        public readonly mixed $defaultValue = null,
        public readonly bool $hasDefault = false,
    ) {}
}
