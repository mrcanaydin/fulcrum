<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Fulcrum',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
    'supported_locales' => array_values(array_filter(array_map(
        'trim',
        explode(',', $_ENV['APP_SUPPORTED_LOCALES'] ?? 'en,tr')
    ))),
    'lang_path' => dirname(__DIR__) . '/lang',
    'providers' => [
        Fulcrum\Internationalization\InternationalizationServiceProvider::class,
    ],
];
