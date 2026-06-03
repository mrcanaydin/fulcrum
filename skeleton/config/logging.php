<?php

declare(strict_types=1);

return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'file',
    'path' => $_ENV['LOG_FILE_PATH'] ?? dirname(__DIR__) . '/storage/logs/fulcrum.log',
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => $_ENV['LOG_FILE_PATH'] ?? dirname(__DIR__) . '/storage/logs/fulcrum.log',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
