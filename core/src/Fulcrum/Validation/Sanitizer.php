<?php

declare(strict_types=1);

namespace Fulcrum\Validation;

class Sanitizer
{
    /** @param list<string> $filters */
    public function apply(mixed $value, array $filters): mixed
    {
        foreach ($filters as $filter) {
            $value = $this->applyFilter($value, $filter);
        }

        return $value;
    }

    private function applyFilter(mixed $value, string $filter): mixed
    {
        return match ($filter) {
            'trim' => is_string($value) ? trim($value) : $value,
            'strip_tags' => is_string($value) ? strip_tags($value) : $value,
            'lower' => is_string($value) ? strtolower($value) : $value,
            'email' => is_string($value) ? filter_var(trim($value), FILTER_SANITIZE_EMAIL) : $value,
            'url' => is_string($value) ? filter_var(trim($value), FILTER_SANITIZE_URL) : $value,
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default => $value,
        };
    }
}
