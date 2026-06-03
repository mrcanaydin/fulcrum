<?php

declare(strict_types=1);

namespace Fulcrum\Cache\Stores;

use Fulcrum\Cache\CacheStore;

class ArrayStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expires_at: int|null}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->items[$key])) {
            return $default;
        }

        if ($this->expired($this->items[$key])) {
            unset($this->items[$key]);

            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function put(string $key, mixed $value, int $seconds = 0): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $seconds > 0 ? time() + $seconds : null,
        ];
    }

    public function increment(string $key, int $amount = 1, int $seconds = 0): int
    {
        $value = $this->get($key, 0);
        $value = is_int($value) || is_float($value) || is_string($value) && is_numeric($value)
            ? (int) $value
            : 0;

        $value += $amount;
        $this->put($key, $value, $seconds);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    /** @param array{value: mixed, expires_at: int|null} $item */
    private function expired(array $item): bool
    {
        return $item['expires_at'] !== null && $item['expires_at'] <= time();
    }
}
