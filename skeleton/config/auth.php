<?php

declare(strict_types=1);

return [
    'token_abilities' => array_values(array_filter(array_map(
        'trim',
        explode(',', $_ENV['AUTH_TOKEN_ABILITIES'] ?? 'users:read,uploads:create')
    ))),
    'token_ttl_seconds' => (int) ($_ENV['AUTH_TOKEN_TTL_SECONDS'] ?? 2592000),
    'require_verified_email' => filter_var($_ENV['AUTH_REQUIRE_VERIFIED_EMAIL'] ?? false, FILTER_VALIDATE_BOOL),
    'login_rate_limit' => [
        'max_attempts' => (int) ($_ENV['AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS'] ?? 5),
        'decay_seconds' => (int) ($_ENV['AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS'] ?? 900),
    ],
];
