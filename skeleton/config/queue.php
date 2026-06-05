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
            'failed_table' => $_ENV['QUEUE_FAILED_JOBS_TABLE'] ?? 'failed_jobs',
            'queue' => $_ENV['QUEUE_NAME'] ?? 'default',
            'retry_after' => (int) ($_ENV['QUEUE_RETRY_AFTER'] ?? 90),
        ],
    ],
    'worker' => [
        'tries' => (int) ($_ENV['QUEUE_TRIES'] ?? 3),
        'timeout' => (int) ($_ENV['QUEUE_TIMEOUT'] ?? 60),
        'backoff' => (int) ($_ENV['QUEUE_BACKOFF'] ?? 5),
        'max_backoff' => (int) ($_ENV['QUEUE_MAX_BACKOFF'] ?? 300),
    ],
];
