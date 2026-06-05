<?php

declare(strict_types=1);

return [
    'table' => $_ENV['SUBSCRIPTION_EVENTS_TABLE'] ?? 'subscription_events',
    'retention_seconds' => (int) ($_ENV['SUBSCRIPTION_RETENTION_SECONDS'] ?? 86400),
    'topics' => [
        'user.created' => [
            'authenticated' => true,
            'abilities' => ['users:read'],
        ],
    ],
    'publish' => [
        App\Events\UserCreated::class => ['user.created'],
    ],
];
