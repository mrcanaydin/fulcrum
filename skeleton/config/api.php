<?php

declare(strict_types=1);

$defaultCorsOrigins = ($_ENV['APP_ENV'] ?? 'production') === 'production' ? '' : '*';

return [
    'max_body_bytes' => (int) ($_ENV['API_MAX_BODY_BYTES'] ?? 1048576),
    'trusted_proxies' => array_filter(array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? ''))),
    'cors' => [
        'enabled' => filter_var($_ENV['CORS_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? $defaultCorsOrigins)))),
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Locale', 'Last-Event-ID', 'X-Requested-With', 'X-Request-Id'],
        'max_age' => 86400,
    ],
    'rate_limit' => [
        'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
        'max_attempts' => (int) ($_ENV['RATE_LIMIT_MAX_ATTEMPTS'] ?? 60),
        'decay_seconds' => (int) ($_ENV['RATE_LIMIT_DECAY_SECONDS'] ?? 60),
    ],
];
