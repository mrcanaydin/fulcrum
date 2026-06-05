<?php

declare(strict_types=1);

return [
    'types' => [
        App\GraphQL\HealthQuery::class,
        App\GraphQL\UserType::class,
        App\GraphQL\UserEdge::class,
        App\GraphQL\UserConnection::class,
        App\GraphQL\UserQuery::class,
        App\GraphQL\UserMutation::class,
    ],
    'scalars' => [],
    'observability' => [
        'slow_resolver_ms' => (float) ($_ENV['GRAPHQL_SLOW_RESOLVER_MS'] ?? 100),
    ],
    'security' => [
        'max_depth' => (int) ($_ENV['GRAPHQL_MAX_DEPTH'] ?? 12),
        'max_complexity' => (int) ($_ENV['GRAPHQL_MAX_COMPLEXITY'] ?? 200),
        'max_aliases' => (int) ($_ENV['GRAPHQL_MAX_ALIASES'] ?? 20),
        'max_operations' => (int) ($_ENV['GRAPHQL_MAX_OPERATIONS'] ?? 1),
        'max_execution_ms' => (int) ($_ENV['GRAPHQL_MAX_EXECUTION_MS'] ?? 0),
        'introspection' => filter_var($_ENV['GRAPHQL_INTROSPECTION'] ?? ($_ENV['APP_ENV'] ?? 'production') !== 'production', FILTER_VALIDATE_BOOL),
    ],
];
