<?php

declare(strict_types=1);

namespace Fulcrum\Support;

/**
 * String helper utilities.
 */
class Str
{
    // ─── Case conversion ─────────────────────────────────────────────────────

    public static function camel(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return lcfirst(str_replace(' ', '', $value));
    }

    public static function pascal(string $value): string
    {
        return ucfirst(static::camel($value));
    }

    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = (string) preg_replace('/\s+/u', '', ucwords($value));
            $value = (string) preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);
            $value = mb_strtolower($value);
        }
        return $value;
    }

    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE);
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value);
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value);
    }

    // ─── Inspection ──────────────────────────────────────────────────────────

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function length(string $value): int
    {
        return mb_strlen($value);
    }

    public static function isEmpty(string $value): bool
    {
        return trim($value) === '';
    }

    // ─── Manipulation ────────────────────────────────────────────────────────

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit)) . $end;
    }

    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[^\pL\pN\s-]/u', '', $value);
        $value = (string) preg_replace('/[\s_]+/', $separator, $value);
        $value = trim($value, $separator);
        return $value;
    }

    public static function random(int $length = 40): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function plural(string $value, int $count = 2): string
    {
        return $count === 1 ? $value : ($value . 's');
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }
}
