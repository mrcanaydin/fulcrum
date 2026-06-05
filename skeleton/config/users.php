<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'enabled' => filter_var($_ENV['USER_EMAIL_VERIFICATION_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
        'expires_minutes' => (int) ($_ENV['USER_EMAIL_VERIFICATION_EXPIRES_MINUTES'] ?? 60),
        'rate_limit' => [
            'max_attempts' => (int) ($_ENV['USER_EMAIL_VERIFICATION_RATE_LIMIT_MAX_ATTEMPTS'] ?? 5),
            'decay_seconds' => (int) ($_ENV['USER_EMAIL_VERIFICATION_RATE_LIMIT_DECAY_SECONDS'] ?? 3600),
        ],
    ],
];
