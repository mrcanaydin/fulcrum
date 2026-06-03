<?php

declare(strict_types=1);

namespace Fulcrum\Cache\Stores;

use Fulcrum\Cache\CacheStore;
use RuntimeException;

class FileStore implements CacheStore
{
    public function __construct(private readonly string $root)
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0777, true) && !is_dir($this->root)) {
            throw new RuntimeException("Cache directory [{$this->root}] could not be created.");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->read($key);

        if ($item === null) {
            return $default;
        }

        if ($this->expired($item)) {
            $this->forget($key);

            return $default;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, int $seconds = 0): void
    {
        $this->write($key, [
            'value' => $value,
            'expires_at' => $seconds > 0 ? time() + $seconds : null,
        ]);
    }

    public function increment(string $key, int $amount = 1, int $seconds = 0): int
    {
        $path = $this->path($key);
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Cache file [{$path}] could not be opened.");
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $item = $this->decode(is_string($contents) ? $contents : '');

            if ($item === null || $this->expired($item)) {
                $item = ['value' => 0, 'expires_at' => $seconds > 0 ? time() + $seconds : null];
            }

            $value = $item['value'];
            $value = is_int($value) || is_float($value) || is_string($value) && is_numeric($value)
                ? (int) $value
                : 0;
            $value += $amount;

            $item['value'] = $value;
            $item['expires_at'] = $item['expires_at'] ?? ($seconds > 0 ? time() + $seconds : null);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, serialize($item));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $value;
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function clear(): void
    {
        foreach (glob(rtrim($this->root, '/') . '/*.cache') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /** @return array{value: mixed, expires_at: int|null}|null */
    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $this->decode(is_string($contents) ? $contents : '');
    }

    /** @param array{value: mixed, expires_at: int|null} $item */
    private function write(string $key, array $item): void
    {
        $path = $this->path($key);

        if (file_put_contents($path, serialize($item), LOCK_EX) === false) {
            throw new RuntimeException("Cache file [{$path}] could not be written.");
        }
    }

    /** @return array{value: mixed, expires_at: int|null}|null */
    private function decode(string $contents): ?array
    {
        if ($contents === '') {
            return null;
        }

        $decoded = unserialize($contents, ['allowed_classes' => false]);

        if (!is_array($decoded) || !array_key_exists('value', $decoded) || !array_key_exists('expires_at', $decoded)) {
            return null;
        }

        if ($decoded['expires_at'] !== null && !is_int($decoded['expires_at'])) {
            return null;
        }

        return [
            'value' => $decoded['value'],
            'expires_at' => $decoded['expires_at'],
        ];
    }

    /** @param array{value: mixed, expires_at: int|null} $item */
    private function expired(array $item): bool
    {
        return $item['expires_at'] !== null && $item['expires_at'] <= time();
    }

    private function path(string $key): string
    {
        return rtrim($this->root, '/') . '/' . sha1($key) . '.cache';
    }
}
