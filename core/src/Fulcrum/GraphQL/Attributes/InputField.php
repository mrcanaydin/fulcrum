<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class InputField
{
    public function __construct(
        public readonly string $type = 'String',
        public readonly string $name = '',
        public readonly string $description = '',
        public readonly mixed $defaultValue = null,
    ) {}
}
