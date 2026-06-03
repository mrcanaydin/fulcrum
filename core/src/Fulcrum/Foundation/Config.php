<?php

declare(strict_types=1);

namespace Fulcrum\Foundation;

use Dotenv\Dotenv;

/**
 * Environment-aware configuration loader.
 *
 * Reads .env via phpdotenv, then loads every PHP file under config/
 * into a keyed array. Values are accessed via dot notation:
 *   $config->get('database.host')
 */
class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $configPath, string $envFile = '')
    {
        $this->loadEnv($envFile);
        $this->loadFiles($configPath);
    }

    // ─── Access ─────────────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->items;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $parts   = explode('.', $key);
        $current = &$this->items;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    // ─── Loaders ────────────────────────────────────────────────────────────

    private function loadEnv(string $envFile): void
    {
        if ($envFile === '' || !file_exists($envFile)) {
            return;
        }

        Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
    }

    private function loadFiles(string $configPath): void
    {
        if (!is_dir($configPath)) {
            return;
        }

        foreach (glob(rtrim($configPath, '/') . '/*.php') ?: [] as $file) {
            $key               = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }
}
