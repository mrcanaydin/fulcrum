<?php

declare(strict_types=1);

namespace Fulcrum\Cache;

use Fulcrum\Cache\Stores\ArrayStore;
use Fulcrum\Cache\Stores\FileStore;
use Fulcrum\Foundation\Config;
use InvalidArgumentException;

class CacheManager
{
    /** @var array<string, CacheStore> */
    private array $stores = [];

    public function __construct(private readonly Config $config) {}

    public function store(?string $name = null): CacheStore
    {
        $name ??= $this->getDefaultStore();

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->makeStore($name);
        }

        return $this->stores[$name];
    }

    public function getDefaultStore(): string
    {
        $default = $this->config->get('cache.default', 'array');

        return is_string($default) && $default !== '' ? $default : 'array';
    }

    public function extend(string $name, CacheStore $store): void
    {
        $this->stores[$name] = $store;
    }

    private function makeStore(string $name): CacheStore
    {
        $config = $this->config->get("cache.stores.{$name}", ['driver' => $name]);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Cache store [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? null;

        if (!is_string($driver) || $driver === '') {
            throw new InvalidArgumentException("Cache store [{$name}] requires a driver.");
        }

        return match ($driver) {
            'array' => new ArrayStore(),
            'file' => new FileStore($this->stringConfig($config, 'path', $this->defaultFilePath())),
            default => throw new InvalidArgumentException("Unsupported cache driver [{$driver}]."),
        };
    }

    /** @param array<string, mixed> $config */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function defaultFilePath(): string
    {
        $path = $this->config->get('cache.path', getcwd() . '/storage/cache');

        return is_string($path) && $path !== '' ? $path : getcwd() . '/storage/cache';
    }
}
