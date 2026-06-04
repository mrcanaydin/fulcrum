<?php

declare(strict_types=1);

use Fulcrum\Cache\CacheManager;
use Fulcrum\Cache\CacheServiceProvider;
use Fulcrum\Cache\Stores\ArrayStore;
use Fulcrum\Cache\Stores\FileStore;
use Fulcrum\Cache\Stores\RedisStore;
use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;

it('stores values in the array cache', function () {
    $store = new ArrayStore();

    $store->put('name', 'Fulcrum', 60);

    expect($store->get('name'))->toBe('Fulcrum')
        ->and($store->increment('hits', 2, 60))->toBe(2)
        ->and($store->increment('hits', 3, 60))->toBe(5);
});

it('persists values in the file cache', function () {
    $root = sys_get_temp_dir() . '/fulcrum-cache-' . bin2hex(random_bytes(6));
    $store = new FileStore($root);

    $store->put('token', 'abc', 60);

    expect((new FileStore($root))->get('token'))->toBe('abc')
        ->and($store->increment('hits', 1, 60))->toBe(1)
        ->and((new FileStore($root))->increment('hits', 1, 60))->toBe(2);

    $store->clear();
    rmdir($root);
});

it('resolves and caches configured stores', function () {
    $root = sys_get_temp_dir() . '/fulcrum-cache-' . bin2hex(random_bytes(6));
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'file');
    $config->set('cache.stores.file', [
        'driver' => 'file',
        'path' => $root,
    ]);

    $manager = new CacheManager($config);
    $store = $manager->store();

    expect($store)->toBeInstanceOf(FileStore::class)
        ->and($manager->store())->toBe($store);

    $store->clear();
    rmdir($root);
});

it('resolves configured redis stores without connecting eagerly', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'redis');
    $config->set('cache.stores.redis', [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'prefix' => 'fulcrum-test:',
    ]);

    expect((new CacheManager($config))->store())->toBeInstanceOf(RedisStore::class);
});

it('registers the cache manager in the container', function () {
    $container = new Container();
    $container->instance(Config::class, new Config(__DIR__ . '/missing'));

    (new CacheServiceProvider($container))->register();

    expect($container->make(CacheManager::class))->toBeInstanceOf(CacheManager::class);
});
