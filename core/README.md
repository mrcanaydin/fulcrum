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

Browser previews can open `GET /` for API metadata. Infrastructure probes can use `GET /health`. GraphQL operations execute through `POST /graphql`.

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
- `redis` via `predis/predis` for shared cache and rate limiting

Applications choose their own cache keys and invalidation strategy inside their models, resolvers, and domain services.

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
./vendor/bin/fulcrum make:model Post
./vendor/bin/fulcrum make:resource Post title:string published:boolean
./vendor/bin/fulcrum make:seeder UserSeeder
./vendor/bin/fulcrum make:factory UserFactory
./vendor/bin/fulcrum db:seed
```

Migrations live in `database/migrations` and return an implementation of `Fulcrum\Database\Migrations\Migration`.

Fulcrum's auth package includes an executable `personal_access_tokens` migration at `src/Fulcrum/Auth/Migrations/create_personal_access_tokens_table.php` for applications that enable token auth tables.

Seeders live in `database/seeders`, implement `Fulcrum\Database\Seeders\Seeder`, and default to `Database\Seeders\DatabaseSeeder`. Factories live in `database/factories` and extend `Fulcrum\Database\Factories\Factory`.

## Models & Relationships

Fulcrum includes a lightweight, Laravel-inspired model layer for API code that should not need raw SQL.

```php
use App\Models\User;

$user = User::find(1);
$users = User::query()->where('active', true)->latest()->limit(20)->toArray();
$created = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
```

Models can define relationships:

```php
use Fulcrum\Database\Model;
use Fulcrum\Database\Relations\HasMany;

class User extends Model
{
    protected string $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
```

`make:resource` creates a model plus GraphQL type/query/mutation CRUD scaffolding. Register the generated GraphQL classes in `config/graphql.php`.

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
