<?php

declare(strict_types=1);

namespace Fulcrum\Pagination;

final class Cursor
{
    public static function encode(string $column, mixed $value): string
    {
        $json = json_encode([
            'v' => 1,
            'column' => $column,
            'value' => $value,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{column: string, value: mixed} */
    public static function decode(string $cursor): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;

        if (!is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || !isset($payload['column'])
            || !is_string($payload['column'])
            || !array_key_exists('value', $payload)
        ) {
            throw new InvalidCursorException();
        }

        return [
            'column' => $payload['column'],
            'value' => $payload['value'],
        ];
    }
}
