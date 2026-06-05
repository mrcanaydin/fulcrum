<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\GraphQL;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\SchemaRegistry;
use GraphQL\Utils\BuildSchema;

function schemaRegistry(string $sdl): SchemaRegistry
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array']);
    $cache = new CacheManager($config);

    return new SchemaRegistry(new Executor(BuildSchema::build($sdl), $config), $cache, $config);
}

it('validates exports and caches canonical schema snapshots', function () {
    $registry = schemaRegistry('type Query { hello: String! }');
    $path = tempnam(sys_get_temp_dir(), 'fulcrum-schema-');

    $registry->validate();
    $hash = $registry->export($path);
    $snapshot = $registry->cachedSnapshot();

    expect(file_get_contents($path))->toContain('type Query')
        ->and($snapshot['hash'] ?? null)->toBe($hash)
        ->and($snapshot['sdl'] ?? null)->toBe($registry->sdl());

    unlink($path);
});

it('detects breaking schema changes from an SDL baseline', function () {
    $baseline = tempnam(sys_get_temp_dir(), 'fulcrum-schema-baseline-');
    file_put_contents($baseline, 'type Query { hello: String! removed: String }');
    $changes = schemaRegistry('type Query { hello: String! }')->breakingChanges($baseline);

    expect($changes)->not->toBeEmpty()
        ->and(array_column($changes, 'description')[0])->toContain('removed');

    unlink($baseline);
});
