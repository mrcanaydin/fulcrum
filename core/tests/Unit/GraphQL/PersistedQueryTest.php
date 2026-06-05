<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\GraphQL;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\PersistedQueryException;
use Fulcrum\GraphQL\PersistedQueryManager;

function persistedQueryManager(?string $allowListPath = null, bool $allowList = false): PersistedQueryManager
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array']);
    $config->set('graphql.persisted_queries.enabled', true);
    $config->set('graphql.persisted_queries.allow_list', $allowList);
    $config->set('graphql.persisted_queries.allow_list_path', $allowListPath);

    return new PersistedQueryManager($config, new CacheManager($config));
}

it('registers and resolves automatic persisted queries by sha256 hash', function () {
    $manager = persistedQueryManager();
    $query = 'query Health { health }';
    $hash = hash('sha256', $query);
    $extensions = ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]];

    expect($manager->resolve($query, $extensions))->toBe($query)
        ->and($manager->resolve('', $extensions))->toBe($query);
});

it('returns typed persisted query errors for misses and hash mismatches', function () {
    $manager = persistedQueryManager();
    $hash = str_repeat('a', 64);
    $extensions = ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]];

    expect(fn () => $manager->resolve('', $extensions))
        ->toThrow(PersistedQueryException::class, 'Persisted query was not found.')
        ->and(fn () => $manager->resolve('{ health }', $extensions))
        ->toThrow(PersistedQueryException::class, 'does not match');
});

it('enforces a deployed persisted query allow-list', function () {
    $query = 'query Health { health }';
    $hash = hash('sha256', $query);
    $path = tempnam(sys_get_temp_dir(), 'fulcrum-allow-list-');
    file_put_contents($path, json_encode([$hash => $query]));
    $manager = persistedQueryManager($path, true);

    expect($manager->resolve('', ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]]))->toBe($query)
        ->and(fn () => $manager->resolve('{ other }', [
            'persistedQuery' => ['version' => 1, 'sha256Hash' => hash('sha256', '{ other }')],
        ]))->toThrow(PersistedQueryException::class, 'not allowed')
        ->and(fn () => $manager->resolve($query, []))->toThrow(PersistedQueryException::class, 'hash is required');

    unlink($path);
});
