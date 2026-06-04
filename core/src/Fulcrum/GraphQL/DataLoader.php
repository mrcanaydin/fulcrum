<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Closure;

class DataLoader
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var Closure(list<mixed>): array<string, mixed> */
    private readonly Closure $batchLoad;

    /** @param callable(list<mixed>): array<string, mixed> $batchLoad */
    public function __construct(callable $batchLoad)
    {
        $this->batchLoad = $batchLoad(...);
    }

    public function load(mixed $key): mixed
    {
        $results = $this->loadMany([$key]);
        $cacheKey = $this->cacheKey($key);

        return $results[$cacheKey] ?? null;
    }

    /**
     * @param list<mixed> $keys
     * @return array<string, mixed>
     */
    public function loadMany(array $keys): array
    {
        $missing = [];

        foreach ($keys as $key) {
            $cacheKey = $this->cacheKey($key);

            if (!array_key_exists($cacheKey, $this->cache)) {
                $missing[$cacheKey] = $key;
            }
        }

        if ($missing !== []) {
            $loaded = ($this->batchLoad)(array_values($missing));

            foreach ($missing as $cacheKey => $key) {
                $this->cache[$cacheKey] = $loaded[$cacheKey] ?? null;
            }
        }

        $results = [];

        foreach ($keys as $key) {
            $cacheKey = $this->cacheKey($key);
            $results[$cacheKey] = $this->cache[$cacheKey] ?? null;
        }

        return $results;
    }

    private function cacheKey(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : md5(serialize($key));
    }
}
