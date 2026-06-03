<?php

declare(strict_types=1);

namespace Fulcrum\Cache;

interface CacheStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, int $seconds = 0): void;

    public function increment(string $key, int $amount = 1, int $seconds = 0): int;

    public function forget(string $key): void;

    public function clear(): void;
}
