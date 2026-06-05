<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\ValidationRule;

final class QuerySafetyResult
{
    /** @param array<ValidationRule> $validationRules */
    public function __construct(
        public readonly DocumentNode $document,
        public readonly array $validationRules,
        public readonly QueryComplexity $complexityRule,
    ) {}
}
