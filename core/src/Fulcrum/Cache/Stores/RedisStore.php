<?php

declare(strict_types=1);

namespace Fulcrum\Cache\Stores;

use Fulcrum\Cache\CacheStore;
use Predis\Client;

class RedisStore implements CacheStore
{
    public function __construct(
        private readonly Client $client,
        private readonly string $prefix = 'fulcrum:',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->key($key));

        if (!is_string($value)) {
            return $default;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $decoded = unserialize($value, ['allowed_classes' => false]);

        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return $default;
        }

        return $decoded['value'];
    }

    public function put(string $key, mixed $value, int $seconds = 0): void
    {
        $payload = serialize(['value' => $value]);
        $redisKey = $this->key($key);

        if ($seconds > 0) {
            $this->client->setex($redisKey, $seconds, $payload);
            return;
        }

        $this->client->set($redisKey, $payload);
    }

    public function increment(string $key, int $amount = 1, int $seconds = 0): int
    {
        $redisKey = $this->key($key);
        $value = $this->client->incrby($redisKey, $amount);

        if ($seconds > 0 && $this->ttl($redisKey) < 0) {
            $this->client->expire($redisKey, $seconds);
        }

        return is_int($value) ? $value : (int) $value;
    }

    public function forget(string $key): void
    {
        $this->client->del([$this->key($key)]);
    }

    public function clear(): void
    {
        $keys = $this->client->keys($this->key('*'));

        if (!is_array($keys)) {
            return;
        }

        $keys = array_values(array_filter($keys, is_string(...)));

        if ($keys !== []) {
            $this->client->del($keys);
        }
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function ttl(string $key): int
    {
        $ttl = $this->client->ttl($key);

        return is_int($ttl) ? $ttl : (int) $ttl;
    }
}
