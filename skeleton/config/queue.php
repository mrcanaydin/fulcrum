<?php

declare(strict_types=1);

return [
    'default' => $_ENV['QUEUE_CONNECTION'] ?? 'sync',
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'table' => $_ENV['QUEUE_JOBS_TABLE'] ?? 'jobs',
            'queue' => $_ENV['QUEUE_NAME'] ?? 'default',
        ],
    ],
];
