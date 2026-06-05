<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\PersistedQueryException;

final class PersistedQueryManager
{
    public function __construct(
        private readonly Config $config,
        private readonly CacheManager $cache,
    ) {}

    /** @param array<string, mixed> $extensions */
    public function resolve(string $query, array $extensions): string
    {
        $hash = $this->hash($extensions);
        $allowList = $this->allowList();
        $allowListMode = (bool) $this->config->get('graphql.persisted_queries.allow_list', false);
        $enabled = (bool) $this->config->get('graphql.persisted_queries.enabled', true);

        if ($hash === null) {
            if ($allowListMode) {
                throw new PersistedQueryException(
                    'Persisted query hash is required.',
                    'PERSISTED_QUERY_REQUIRED',
                );
            }

            return $query;
        }

        if (!$enabled && !$allowListMode) {
            return $query;
        }

        if ($query === '') {
            $persisted = $allowList[$hash] ?? $this->cache->store()->get($this->cacheKey($hash));

            if (!is_string($persisted) || $persisted === '') {
                throw new PersistedQueryException('Persisted query was not found.', 'PERSISTED_QUERY_NOT_FOUND');
            }

            return $persisted;
        }

        if (!hash_equals($hash, hash('sha256', $query))) {
            throw new PersistedQueryException(
                'Persisted query hash does not match the supplied query.',
                'PERSISTED_QUERY_HASH_MISMATCH',
            );
        }

        if ($allowListMode && !isset($allowList[$hash])) {
            throw new PersistedQueryException('Persisted query is not allowed.', 'PERSISTED_QUERY_NOT_ALLOWED');
        }

        if ($enabled && !$allowListMode) {
            $ttl = $this->config->get('graphql.persisted_queries.ttl', 86400);
            $this->cache->store()->put($this->cacheKey($hash), $query, is_numeric($ttl) ? max(0, (int) $ttl) : 86400);
        }

        return $query;
    }

    /** @param array<string, mixed> $extensions */
    private function hash(array $extensions): ?string
    {
        $persisted = $extensions['persistedQuery'] ?? null;

        if (!is_array($persisted)) {
            return null;
        }

        $version = $persisted['version'] ?? null;
        $hash = $persisted['sha256Hash'] ?? null;

        if (!is_numeric($version) || (int) $version !== 1 || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) {
            throw new PersistedQueryException('Persisted query extension is invalid.', 'PERSISTED_QUERY_INVALID');
        }

        return strtolower($hash);
    }

    /** @return array<string, string> */
    private function allowList(): array
    {
        $path = $this->config->get('graphql.persisted_queries.allow_list_path');

        if (!is_string($path) || $path === '' || !is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new PersistedQueryException('Persisted query allow-list is invalid.', 'PERSISTED_QUERY_ALLOW_LIST_INVALID');
        }

        $queries = [];
        foreach ($decoded as $hash => $query) {
            if (is_string($hash) && is_string($query) && hash_equals(strtolower($hash), hash('sha256', $query))) {
                $queries[strtolower($hash)] = $query;
            }
        }

        return $queries;
    }

    private function cacheKey(string $hash): string
    {
        return 'graphql:persisted:' . $hash;
    }
}
