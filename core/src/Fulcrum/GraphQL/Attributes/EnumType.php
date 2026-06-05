<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class EnumType
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $description = '',
    ) {}
}
