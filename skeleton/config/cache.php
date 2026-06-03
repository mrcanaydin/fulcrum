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
    ],
];
