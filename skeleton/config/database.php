<?php

declare(strict_types=1);

return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
            'database' => $_ENV['DB_DATABASE'] ?? 'fulcrum',
            'username' => $_ENV['DB_USERNAME'] ?? 'fulcrum',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'prefix' => '',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? 'fulcrum',
            'username' => $_ENV['DB_USERNAME'] ?? 'fulcrum',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'prefix' => '',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? dirname(__DIR__) . '/storage/database.sqlite',
            'prefix' => '',
        ],
    ],
];
