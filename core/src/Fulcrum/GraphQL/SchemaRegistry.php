<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Foundation\Config;
use GraphQL\Type\Schema;
use GraphQL\Utils\BreakingChangesFinder;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use RuntimeException;

final class SchemaRegistry
{
    public function __construct(
        private readonly Executor $executor,
        private readonly CacheManager $cache,
        private readonly Config $config,
    ) {}

    public function schema(): Schema
    {
        return $this->executor->schema();
    }

    public function validate(): void
    {
        $this->schema()->assertValid();
    }

    public function sdl(): string
    {
        $this->validate();

        return SchemaPrinter::doPrint($this->schema());
    }

    /** @return array{hash: string, sdl: string} */
    public function cacheSnapshot(): array
    {
        $sdl = $this->sdl();
        $snapshot = ['hash' => hash('sha256', $sdl), 'sdl' => $sdl];
        $ttl = $this->config->get('graphql.schema_cache.ttl', 86400);
        $this->cache->store()->put(
            $this->cacheKey(),
            $snapshot,
            is_numeric($ttl) ? max(0, (int) $ttl) : 86400,
        );

        return $snapshot;
    }

    /** @return array{hash: string, sdl: string}|null */
    public function cachedSnapshot(): ?array
    {
        $snapshot = $this->cache->store()->get($this->cacheKey());

        if (!is_array($snapshot) || !is_string($snapshot['hash'] ?? null) || !is_string($snapshot['sdl'] ?? null)) {
            return null;
        }

        return ['hash' => $snapshot['hash'], 'sdl' => $snapshot['sdl']];
    }

    public function export(string $path): string
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Schema export directory [{$directory}] could not be created.");
        }

        $snapshot = $this->cacheSnapshot();
        if (file_put_contents($path, $snapshot['sdl']) === false) {
            throw new RuntimeException("Schema could not be written to [{$path}].");
        }

        return $snapshot['hash'];
    }

    /** @return list<array{type: string, description: string}> */
    public function breakingChanges(string $baselinePath): array
    {
        if (!is_file($baselinePath)) {
            throw new RuntimeException("Schema baseline [{$baselinePath}] does not exist.");
        }

        $oldSchema = BuildSchema::build((string) file_get_contents($baselinePath));
        $oldSchema->assertValid();
        $changes = [];

        foreach (BreakingChangesFinder::findBreakingChanges($oldSchema, $this->schema()) as $change) {
            $type = $change['type'] ?? '';
            $description = $change['description'] ?? '';

            if (is_string($type) && is_string($description)) {
                $changes[] = ['type' => $type, 'description' => $description];
            }
        }

        return $changes;
    }

    private function cacheKey(): string
    {
        $key = $this->config->get('graphql.schema_cache.key', 'graphql:schema:snapshot');

        return is_string($key) && $key !== '' ? $key : 'graphql:schema:snapshot';
    }
}
