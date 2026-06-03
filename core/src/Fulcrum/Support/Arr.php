<?php

declare(strict_types=1);

namespace Fulcrum\Support;

/**
 * Array helper utilities.
 */
class Arr
{
    // ─── Access ──────────────────────────────────────────────────────────────

    /**
     * Get a value using dot notation, with an optional default.
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a value using dot notation, creating nested arrays as needed.
     */
    public static function set(array &$array, string $key, mixed $value): array
    {
        $keys    = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }

        return $array;
    }

    public static function has(array $array, string $key): bool
    {
        $sentinel = new \stdClass();
        return static::get($array, $key, $sentinel) !== $sentinel;
    }

    public static function forget(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($array[$k]) || !is_array($array[$k])) {
                return;
            }
            $array = &$array[$k];
        }
        unset($array[array_shift($keys)]);
    }

    // ─── Manipulation ────────────────────────────────────────────────────────

    public static function only(array $array, string|array $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function except(array $array, string|array $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    public static function flatten(array $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    public static function pluck(array $array, string $key): array
    {
        return array_column($array, $key);
    }

    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : reset($array);
        }
        foreach ($array as $item) {
            if ($callback($item)) {
                return $item;
            }
        }
        return $default;
    }

    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }
        return static::first(array_reverse($array), $callback, $default);
    }

    public static function wrap(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    public static function keyBy(array $array, string $key): array
    {
        return array_column($array, null, $key);
    }

    public static function groupBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $groupKey          = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $result[$groupKey][] = $item;
        }
        return $result;
    }

    // ─── Inspection ──────────────────────────────────────────────────────────

    public static function isAssoc(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public static function isList(array $array): bool
    {
        return !static::isAssoc($array);
    }
}
