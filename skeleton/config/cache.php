<?php

declare(strict_types=1);

return [
    'default' => $_ENV['CACHE_STORE'] ?? 'file',
    'path' => $_ENV['CACHE_FILE_PATH'] ?? dirname(__DIR__) . '/storage/cache',
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'file' => [
            'driver' => 'file',
            'path' => $_ENV['CACHE_FILE_PATH'] ?? dirname(__DIR__) . '/storage/cache',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_CACHE_DB'] ?? 0),
            'username' => ($_ENV['REDIS_USERNAME'] ?? '') !== '' ? $_ENV['REDIS_USERNAME'] : null,
            'password' => ($_ENV['REDIS_PASSWORD'] ?? '') !== '' ? $_ENV['REDIS_PASSWORD'] : null,
            'prefix' => $_ENV['REDIS_CACHE_PREFIX'] ?? 'fulcrum:',
        ],
    ],
];
