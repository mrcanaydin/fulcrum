<?php

declare(strict_types=1);

use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;
use Fulcrum\Storage\StorageManager;
use Fulcrum\Storage\StorageServiceProvider;

it('resolves and caches the default local disk', function () {
    $root = sys_get_temp_dir() . '/fulcrum-storage-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);

    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 'local');
    $config->set('storage.disks.local', [
        'driver' => 'local',
        'root' => $root,
    ]);

    $manager = new StorageManager($config);
    $disk = $manager->disk();

    $disk->write('hello.txt', 'Fulcrum');

    expect($disk->read('hello.txt'))->toBe('Fulcrum')
        ->and($manager->disk())->toBe($disk);

    unlink($root . '/hello.txt');
    rmdir($root);
});

it('registers the storage manager in the container', function () {
    $container = new Container();
    $container->instance(Config::class, new Config(__DIR__ . '/missing'));

    (new StorageServiceProvider($container))->register();

    expect($container->make(StorageManager::class))->toBeInstanceOf(StorageManager::class);
});
