<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Scalars;

use DateTimeImmutable;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

final class BuiltInScalars
{
    /** @return array<string, ScalarType> */
    public static function all(): array
    {
        return [
            'Date' => self::stringScalar('Date', self::date(...)),
            'DateTime' => self::stringScalar('DateTime', self::dateTime(...)),
            'JSON' => new CustomScalarType([
                'name' => 'JSON',
                'description' => 'A JSON value.',
                'serialize' => static fn (mixed $value): mixed => $value,
                'parseValue' => static fn (mixed $value): mixed => $value,
                'parseLiteral' => static fn (Node $node, ?array $variables = null): mixed => AST::valueFromASTUntyped($node, $variables),
            ]),
            'Decimal' => self::stringScalar('Decimal', self::decimal(...)),
            'URL' => self::stringScalar('URL', self::url(...)),
        ];
    }

    private static function stringScalar(string $name, callable $normalise): CustomScalarType
    {
        return new CustomScalarType([
            'name' => $name,
            'serialize' => $normalise,
            'parseValue' => $normalise,
            'parseLiteral' => static function (Node $node) use ($name, $normalise): string {
                $value = AST::valueFromASTUntyped($node);

                if (!is_string($value)) {
                    throw new Error("{$name} must be represented by a string.");
                }

                return $normalise($value);
            },
        ]);
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('Date must be represented by a string.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new Error('Date must use the YYYY-MM-DD format.');
        }

        return $value;
    }

    private static function dateTime(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new Error('DateTime must be a valid ISO 8601 date-time.');
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new Error('DateTime must be a valid ISO 8601 date-time.');
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new Error('DateTime must be a valid ISO 8601 date-time.');
        }

        return $date->format(DATE_ATOM);
    }

    private static function decimal(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || preg_match('/^-?\d+(?:\.\d+)?$/', (string) $value) !== 1) {
            throw new Error('Decimal must be a base-10 numeric string.');
        }

        return (string) $value;
    }

    private static function url(mixed $value): string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new Error('URL must be a valid absolute URL.');
        }

        return $value;
    }
}
