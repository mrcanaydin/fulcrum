# Fulcrum Core

Fulcrum is a headless, GraphQL-first PHP framework core for modern frontend and mobile applications.

## Install

```bash
composer require fulcrum/core
```

For new applications, use the skeleton:

```bash
composer create-project fulcrum/skeleton myapp
cd myapp
php -S 127.0.0.1:8000 -t public
```

## Storage

Fulcrum ships with Flysystem 3-backed storage disks.

```php
use Fulcrum\Storage\StorageManager;

$storage = $container->make(StorageManager::class);
$storage->disk()->write('example.txt', 'Hello Fulcrum');
```

Supported drivers:

- `local` via `league/flysystem-local`
- `s3` via `league/flysystem-aws-s3-v3`

## Cache

Fulcrum includes a lightweight cache manager for API infrastructure and application code.

```php
use Fulcrum\Cache\CacheManager;

$cache = $container->make(CacheManager::class)->store();
$cache->put('feature-flags', ['beta' => true], 300);
```

Supported drivers:

- `array` for process-local testing and ephemeral values
- `file` for simple durable cache storage

## Logging

Fulcrum exposes a small PSR-3-compatible logger manager for API observability.

```php
use Psr\Log\LoggerInterface;

$logger = $container->make(LoggerInterface::class);
$logger->info('GraphQL request completed', ['operation' => 'Health']);
```

Supported drivers:

- `file` for JSON-line application logs
- `null` for tests or intentionally silent deployments

## Events

Fulcrum includes a synchronous, container-aware event dispatcher for API domain hooks.

```php
use Fulcrum\Events\EventDispatcher;

$events = $container->make(EventDispatcher::class);
$events->listen(UserRegistered::class, SendWelcomeEmail::class);
$events->dispatch(new UserRegistered($userId));
```

Listeners can be registered in `events.listeners` config or at runtime. Class listeners are resolved through the container and receive the event payload in `handle()`.

## CLI & Migrations

Fulcrum exposes a small CLI for app maintenance:

```bash
./vendor/bin/fulcrum migrate
./vendor/bin/fulcrum migrate:rollback
./vendor/bin/fulcrum migrate:status
./vendor/bin/fulcrum make:migration create_api_tokens
```

Migrations live in `database/migrations` and return an implementation of `Fulcrum\Database\Migrations\Migration`.

## Validation & Sanitization

Input handling is explicit and API-oriented. Sanitizers run only for configured fields and do not mutate global request data.

```php
use Fulcrum\Validation\Validator;

$input = $validator->validate(
    $args,
    ['email' => 'required|email', 'name' => 'required|string|min:2'],
    ['email' => ['email', 'lower'], 'name' => ['trim', 'strip_tags']]
);
```

Validation failures can be returned as GraphQL-safe errors with a `validation` extension.

## API Middleware

Fulcrum routes API requests through a small middleware pipeline before GraphQL execution. The default stack handles:

- CORS preflight and response headers
- Request IDs with `X-Request-Id`
- Body size limits
- Cache-backed rate limiting by client IP and path
- JSON `Content-Type` enforcement for GraphQL POSTs

Applications can override `api.middleware`, `api.cors`, `api.rate_limit`, `api.max_body_bytes`, and `api.trusted_proxies` in config.

## GraphQL Errors

When `app.debug` is true, GraphQL responses include debug messages and traces. In production, internal exception details are hidden behind opaque error messages.

## Quality

```bash
composer install
./vendor/bin/pest
php -d memory_limit=-1 ./vendor/bin/phpstan analyse
```
