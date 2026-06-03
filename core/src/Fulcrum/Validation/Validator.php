<?php

declare(strict_types=1);

namespace Fulcrum\Validation;

class Validator
{
    public function __construct(private readonly Sanitizer $sanitizer = new Sanitizer()) {}

    /**
     * @param array<string, mixed> $input
     * @param array<string, string|list<string>> $rules
     * @param array<string, list<string>> $sanitize
     * @return array<string, mixed>
     */
    public function validate(array $input, array $rules, array $sanitize = []): array
    {
        $data = $this->sanitize($input, $sanitize);
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            foreach ($this->normalizeRules($fieldRules) as $rule) {
                $message = $this->validateRule($field, $data[$field] ?? null, $rule, array_key_exists($field, $data));

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $filters
     * @return array<string, mixed>
     */
    public function sanitize(array $input, array $filters): array
    {
        foreach ($filters as $field => $fieldFilters) {
            if (array_key_exists($field, $input)) {
                $input[$field] = $this->sanitizer->apply($input[$field], $fieldFilters);
            }
        }

        return $input;
    }

    /** @return list<string> */
    /**
     * @param string|list<string> $rules
     * @return list<string>
     */
    private function normalizeRules(string|array $rules): array
    {
        return is_string($rules) ? explode('|', $rules) : array_values($rules);
    }

    private function validateRule(string $field, mixed $value, string $rule, bool $present): ?string
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        if ($name === 'nullable' && ($value === null || $value === '')) {
            return null;
        }

        if ($name !== 'required' && !$present) {
            return null;
        }

        if ($name !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        return match ($name) {
            'required' => $present && $value !== null && $value !== '' ? null : "{$field} is required.",
            'string' => is_string($value) ? null : "{$field} must be a string.",
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$field} must be a valid email address.",
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) ? null : "{$field} must be a valid URL.",
            'int', 'integer' => is_int($value) ? null : "{$field} must be an integer.",
            'numeric' => is_int($value) || is_float($value) ? null : "{$field} must be numeric.",
            'bool', 'boolean' => is_bool($value) ? null : "{$field} must be true or false.",
            'array' => is_array($value) ? null : "{$field} must be an array.",
            'min' => $this->validateMin($field, $value, $parameter),
            'max' => $this->validateMax($field, $value, $parameter),
            'in' => $this->validateIn($field, $value, $parameter),
            default => null,
        };
    }

    private function validateMin(string $field, mixed $value, ?string $parameter): ?string
    {
        $min = (float) ($parameter ?? 0);

        if (is_string($value)) {
            return strlen($value) >= $min ? null : "{$field} must be at least {$parameter} characters.";
        }

        if (is_int($value) || is_float($value)) {
            return $value >= $min ? null : "{$field} must be at least {$parameter}.";
        }

        return null;
    }

    private function validateMax(string $field, mixed $value, ?string $parameter): ?string
    {
        $max = (float) ($parameter ?? 0);

        if (is_string($value)) {
            return strlen($value) <= $max ? null : "{$field} may not be greater than {$parameter} characters.";
        }

        if (is_int($value) || is_float($value)) {
            return $value <= $max ? null : "{$field} may not be greater than {$parameter}.";
        }

        return null;
    }

    private function validateIn(string $field, mixed $value, ?string $parameter): ?string
    {
        $allowed = $parameter !== null ? explode(',', $parameter) : [];

        if (!is_scalar($value)) {
            return "{$field} must be one of: " . implode(', ', $allowed) . '.';
        }

        return in_array((string) $value, $allowed, true)
            ? null
            : "{$field} must be one of: " . implode(', ', $allowed) . '.';
    }
}
