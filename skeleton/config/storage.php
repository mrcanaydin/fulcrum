<?php

declare(strict_types=1);

return [
    'default' => $_ENV['STORAGE_DISK'] ?? 'local',
    'signed_uploads' => [
        'allowed_disks' => array_values(array_filter(array_map(
            'trim',
            explode(',', $_ENV['SIGNED_UPLOAD_ALLOWED_DISKS'] ?? ($_ENV['STORAGE_DISK'] ?? 'local'))
        ))),
        'allowed_content_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', $_ENV['SIGNED_UPLOAD_ALLOWED_CONTENT_TYPES'] ?? 'image/png,image/jpeg,image/webp,application/pdf')
        ))),
        'min_ttl_seconds' => (int) ($_ENV['SIGNED_UPLOAD_MIN_TTL_SECONDS'] ?? 60),
        'max_ttl_seconds' => (int) ($_ENV['SIGNED_UPLOAD_MAX_TTL_SECONDS'] ?? 900),
    ],
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => $_ENV['STORAGE_LOCAL_ROOT'] ?? dirname(__DIR__) . '/storage/app',
        ],
        's3' => [
            'driver' => 's3',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['AWS_BUCKET'] ?? '',
            'endpoint' => ($_ENV['AWS_ENDPOINT'] ?? '') !== '' ? $_ENV['AWS_ENDPOINT'] : null,
            'use_path_style_endpoint' => filter_var($_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] ?? false, FILTER_VALIDATE_BOOL),
        ],
    ],
];
