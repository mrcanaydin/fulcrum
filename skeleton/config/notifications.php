<?php

declare(strict_types=1);

return [
    'default' => $_ENV['NOTIFICATION_CHANNEL'] ?? 'log',
    'queue' => filter_var($_ENV['NOTIFICATIONS_QUEUE'] ?? true, FILTER_VALIDATE_BOOL),
    'channels' => [
        'log' => [
            'transport' => 'log',
            'path' => $_ENV['NOTIFICATION_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/notifications.log',
        ],
        'webhook' => [
            'transport' => 'webhook',
            'url' => $_ENV['NOTIFICATION_WEBHOOK_URL'] ?? '',
            'token' => ($_ENV['NOTIFICATION_WEBHOOK_TOKEN'] ?? '') !== '' ? $_ENV['NOTIFICATION_WEBHOOK_TOKEN'] : null,
            'timeout' => (int) ($_ENV['NOTIFICATION_WEBHOOK_TIMEOUT'] ?? 10),
        ],
    ],
    'hooks' => [
        App\Events\UserCreated::class => [
            [
                'enabled' => filter_var($_ENV['USER_WELCOME_PUSH_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
                'channel' => $_ENV['USER_WELCOME_PUSH_CHANNEL'] ?? 'log',
                'queue' => true,
                'to' => 'user:{userId}',
                'title' => 'Welcome to Fulcrum',
                'title_key' => 'messages.welcome_title',
                'body' => 'Your account is ready.',
                'body_key' => 'messages.welcome_body',
                'data' => [
                    'type' => 'user.created',
                    'user_id' => '{userId}',
                    'email' => '{email}',
                ],
            ],
        ],
    ],
    'mail_hooks' => [
        App\Events\UserCreated::class => [
            [
                'enabled' => filter_var($_ENV['USER_WELCOME_EMAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
                'mailer' => $_ENV['USER_WELCOME_EMAIL_MAILER'] ?? null,
                'queue' => true,
                'to' => '{email}',
                'subject' => 'Welcome to Fulcrum',
                'subject_key' => 'messages.welcome_title',
                'text' => 'Thanks for creating your account. Your user id is {userId}.',
                'text_key' => 'messages.welcome_email',
            ],
        ],
    ],
];
