<?php

declare(strict_types=1);

namespace Fulcrum\Observability;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Storage\StorageManager;
use Throwable;

final class HealthChecker
{
    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $database,
        private readonly CacheManager $cache,
        private readonly QueueManager $queue,
        private readonly StorageManager $storage,
    ) {}

    public function readiness(): HealthCheckResult
    {
        $checks = [];

        if ($this->enabled('database')) {
            $checks['database'] = $this->probe(function (): array {
                $this->database->connection()->select('SELECT 1 AS health');

                return ['connection' => $this->database->getDefaultConnection()];
            });
        }

        if ($this->enabled('cache')) {
            $checks['cache'] = $this->probe(function (): array {
                $key = 'fulcrum:health:' . bin2hex(random_bytes(8));
                $store = $this->cache->store();
                $store->put($key, 'ok', 30);
                $value = $store->get($key);
                $store->forget($key);

                if ($value !== 'ok') {
                    throw new \RuntimeException('Cache health probe value could not be read.');
                }

                return ['store' => $this->cache->getDefaultStore()];
            });
        }

        if ($this->enabled('queue')) {
            $checks['queue'] = $this->probe(function (): array {
                return $this->queue->metrics() + ['connection' => $this->queue->defaultConnection()];
            });
        }

        if ($this->enabled('storage')) {
            $checks['storage'] = $this->probe(function (): array {
                $path = '.fulcrum-health/' . bin2hex(random_bytes(8));
                $disk = $this->storage->disk();
                $disk->write($path, 'ok');

                try {
                    if ($disk->read($path) !== 'ok') {
                        throw new \RuntimeException('Storage health probe value could not be read.');
                    }
                } finally {
                    if ($disk->fileExists($path)) {
                        $disk->delete($path);
                    }
                }

                return ['disk' => $this->storage->getDefaultDisk()];
            });
        }

        $healthy = true;
        foreach ($checks as $check) {
            if (($check['status'] ?? null) !== 'ok') {
                $healthy = false;
                break;
            }
        }

        return new HealthCheckResult($healthy, $checks);
    }

    private function enabled(string $check): bool
    {
        return (bool) $this->config->get("health.checks.{$check}", true);
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function probe(callable $callback): array
    {
        $startedAt = hrtime(true);

        try {
            $details = $callback();

            return [
                'status' => 'ok',
                'duration_ms' => $this->duration($startedAt),
            ] + $details;
        } catch (Throwable $exception) {
            $result = [
                'status' => 'failed',
                'duration_ms' => $this->duration($startedAt),
                'error' => $exception::class,
            ];

            if ((bool) $this->config->get('app.debug', false)) {
                $result['message'] = $exception->getMessage();
            }

            return $result;
        }
    }

    private function duration(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }
}
