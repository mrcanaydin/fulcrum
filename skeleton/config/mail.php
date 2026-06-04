<?php

declare(strict_types=1);

return [
    'default' => $_ENV['MAIL_MAILER'] ?? 'log',
    'mailers' => [
        'log' => [
            'transport' => 'log',
            'path' => $_ENV['MAIL_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/mail.log',
        ],
        'smtp' => [
            'transport' => 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
            'username' => ($_ENV['MAIL_USERNAME'] ?? '') !== '' ? $_ENV['MAIL_USERNAME'] : null,
            'password' => ($_ENV['MAIL_PASSWORD'] ?? '') !== '' ? $_ENV['MAIL_PASSWORD'] : null,
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
        ],
    ],
];
