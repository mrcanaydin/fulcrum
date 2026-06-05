<?php

declare(strict_types=1);

return [
    'checks' => [
        'database' => filter_var($_ENV['HEALTH_CHECK_DATABASE'] ?? true, FILTER_VALIDATE_BOOL),
        'cache' => filter_var($_ENV['HEALTH_CHECK_CACHE'] ?? true, FILTER_VALIDATE_BOOL),
        'queue' => filter_var($_ENV['HEALTH_CHECK_QUEUE'] ?? true, FILTER_VALIDATE_BOOL),
        'storage' => filter_var($_ENV['HEALTH_CHECK_STORAGE'] ?? true, FILTER_VALIDATE_BOOL),
    ],
];
