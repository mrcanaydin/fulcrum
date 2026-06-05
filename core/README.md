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

## GraphQL Errors

Client-safe GraphQL errors expose a stable `extensions.code`. Clients should branch on this code instead of parsing the human-readable message.

```json
{
  "errors": [
    {
      "message": "The given input was invalid.",
      "extensions": {
        "code": "VALIDATION_FAILED",
        "validation": {
          "email": ["The email must be a valid email address."]
        },
        "requestId": "req-123"
      }
    }
  ]
}
```

Fulcrum reserves these core codes:

- `UNAUTHENTICATED` for requests without a required identity
- `FORBIDDEN` for authenticated callers without permission
- `VALIDATION_FAILED` with field errors in `extensions.validation`
- `NOT_FOUND` for requested resources that do not exist

Throw `Fulcrum\GraphQL\Exceptions\NotFoundException` from application resolvers when absence should be represented as an error. Unexpected exceptions remain masked in production. When request ID middleware is enabled, every GraphQL error includes the same ID returned in the `X-Request-Id` response header.

## GraphQL Types

Fulcrum supports attributed object types, input objects, native PHP enums, field deprecation, and custom scalars.

```php
#[InputObject(name: 'CreatePostInput')]
class CreatePostInput
{
    #[InputField(type: 'String!')]
    public string $title;
}

#[EnumType(name: 'PostStatus')]
enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
```

Built-in custom scalars are `Date`, `DateTime`, `JSON`, `Decimal`, and `URL`. Register application scalars in `config/graphql.php` by name using a `GraphQL\Type\Definition\ScalarType` instance or class:

```php
return [
    'types' => [CreatePostInput::class, PostStatus::class],
    'scalars' => [
        'Money' => App\GraphQL\Scalars\MoneyScalar::class,
    ],
];
```

Add `deprecationReason` to `#[Field]`, `#[Query]`, or `#[Mutation]` to expose schema deprecation metadata.

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

## Mail

Fulcrum includes a small mail manager for API workflows such as verification emails, notifications, and background jobs.

```php
use Fulcrum\Mail\MailManager;
use Fulcrum\Mail\Message;

$mail = $container->make(MailManager::class);
$mail->send(new Message(
    to: 'ada@example.com',
    subject: 'Verify your email',
    text: 'Use this verification token: ...',
));
```

Supported transports:

- `log` writes JSON-line email payloads for local development
- `smtp` sends directly through a configured SMTP server

## Notifications & Message Hooks

Fulcrum notifications are queue-aware and designed for API-side push workflows.

```php
use Fulcrum\Notifications\Notification;
use Fulcrum\Notifications\NotificationManager;

$notifications = $container->make(NotificationManager::class);
$notifications->send(new Notification(
    to: 'user:123',
    title: 'New message',
    body: 'Ada sent you a direct message.',
    data: ['conversation_id' => 'abc'],
));
```

Supported transports:

- `log` writes JSON-line notification payloads for local development
- `webhook` posts JSON payloads to an external push gateway, such as an FCM/APNs service

Applications can also configure event hooks in `notifications.hooks` and `notifications.mail_hooks`. Hook values support placeholders from public event properties, for example `{userId}` and `{email}`. This keeps GraphQL resolvers thin: dispatch a domain event, then decide in config which actions send email or push notifications.

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
php fulcrum migrate
php fulcrum migrate:rollback
php fulcrum migrate:status
php fulcrum make:migration create_api_tokens
php fulcrum make:model Post
php fulcrum make:resource Post title:string published:boolean
php fulcrum make:seeder UserSeeder
php fulcrum make:factory UserFactory
php fulcrum db:seed
php fulcrum schedule:run
php fulcrum queue:work
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

`make:resource` creates a model plus GraphQL type, edge, connection, query, and mutation CRUD scaffolding. Register the generated GraphQL classes in `config/graphql.php`.

Use eager loading to batch relationship queries and avoid N+1 resolver loops:

```php
$users = User::query()->with('posts')->latest()->limit(20)->toArray();
```

GraphQL resolvers also receive a request-scoped DataLoader registry via `RequestContext` for custom batch loaders:

```php
$loader = $context->loaders()->getOrRegister('users.by_id', fn (array $ids) => $usersById);
$user = $loader->load($id);
```

## Cursor Pagination

Model queries provide bounded forward cursor pagination:

```php
$connection = Post::query()->cursorPaginate(
    first: 25,
    after: $args['after'] ?? null,
);

return $connection->toArray();
```

Connections contain `nodes`, `edges`, and `pageInfo`. Cursors are opaque to clients and encode the deterministic cursor column, which defaults to the model primary key. `make:resource` generates resource edge and connection GraphQL types plus a cursor-paginated list query.

Malformed or mismatched cursors return the client-safe GraphQL error code `INVALID_CURSOR`.

## Query Safety

GraphQL documents are parsed and checked before resolver execution. Configure limits under `graphql.security`:

```php
'security' => [
    'max_depth' => 12,
    'max_complexity' => 200,
    'max_aliases' => 20,
    'max_operations' => 1,
    'max_execution_ms' => 0,
    'introspection' => false,
],
```

Rejected operations return stable codes: `QUERY_DEPTH_EXCEEDED`, `QUERY_COMPLEXITY_EXCEEDED`, `ALIAS_LIMIT_EXCEEDED`, `OPERATION_LIMIT_EXCEEDED`, `INTROSPECTION_DISABLED`, or `GRAPHQL_VALIDATION_FAILED`.

Every completed operation logs its operation name, request ID, duration, complexity, and error status. Because Fulcrum currently executes GraphQL synchronously, `max_execution_ms` detects and rejects an over-budget response after execution; use web-server and process-level timeouts for hard cancellation.

Resolver metrics are also logged with resolver class/method, request ID, duration, status, and a slow flag. Configure `graphql.observability.slow_resolver_ms` to elevate slow resolver records to warnings.

## Health Checks

Fulcrum exposes separate infrastructure probes:

- `GET /health/live` checks only that the PHP process can serve a response.
- `GET /health/ready` runs real database, cache, queue, and storage probes.
- `GET /health` and `GET /ready` are readiness aliases.

Readiness performs `SELECT 1`, cache write/read/delete, queue metrics access, and storage write/read/delete. It returns HTTP `503` with per-component status and duration when any enabled dependency fails. Health routes bypass normal API middleware so a failed cache-backed rate limiter cannot hide the actual health result.

Configure individual probes under `health.checks`. Internal exception messages are only included when `app.debug` is enabled.

## Transactions And Reliable Mutations

PDO connections expose `transaction()`, nested transactions through savepoints, and `afterCommit()`. Failed callbacks roll back their database writes and discard side effects waiting for commit.

```php
$result = $db->transaction(function () use ($db, $events, $queues) {
    $id = $db->table('orders')->insert(['status' => 'created']);

    $events->dispatchAfterCommit(new OrderCreated((string) $id));
    $queues->dispatchAfterCommit(new SendOrderEmail((string) $id));

    return $id;
});
```

GraphQL mutations opt in through `#[Mutation]`:

```php
#[Mutation(name: 'createOrder', type: 'Order!', transactional: true)]
public function createOrder(mixed $root, array $args): array
{
    // The resolver commits on success and rolls back when it throws.
}

#[Mutation(name: 'chargeOrder', type: 'Charge!', idempotent: true)]
public function chargeOrder(mixed $root, array $args): array
{
    // idempotent implies transactional
}
```

Idempotent mutations require an `Idempotency-Key` request header and an `idempotency_keys` table. Repeating the same key and arguments replays the stored resolver result; reusing a key with different arguments returns `IDEMPOTENCY_KEY_REUSED`.

The current MongoDB driver does not support transactions. Calling its transaction APIs throws an explicit runtime exception.

## Commands, Scheduling & Queues

Applications can register Laravel-style console commands in `config/console.php`:

```php
return [
    'commands' => [
        App\Console\FetchApiDataCommand::class,
    ],
];
```

Commands extend `Fulcrum\Console\Command`:

```php
class FetchApiDataCommand extends Command
{
    protected string $signature = 'api-data:fetch';

    public function handle(): int
    {
        $sort = $this->stringOption('sort', 'new');
        // Dispatch jobs, call services, import data.
        return self::SUCCESS;
    }
}
```

Schedules live in `config/schedule.php`:

```php
use Fulcrum\Schedule\Schedule;

return [
    Schedule::command('api-data:fetch --sort=new')->everyFiveMinutes(),
];
```

Run due schedules from cron:

```bash
* * * * * cd /path/to/app && php fulcrum schedule:run
```

Jobs implement `Fulcrum\Queue\Job` and define a `handle()` method. The handler is container-aware, so services such as `MailManager` can be type-hinted directly. Supported queue drivers are `sync` and `database`.

The database queue conditionally claims jobs so concurrent workers cannot reserve the same row. Stale reservations become available after `retry_after`. Failed attempts use exponential backoff, and terminal failures move to the `failed_jobs` dead-letter table instead of being discarded.

```bash
php fulcrum queue:work --tries=3 --timeout=60 --backoff=5 --max-backoff=300
php fulcrum queue:status
php fulcrum queue:failed
php fulcrum queue:retry        # retry all failed jobs
php fulcrum queue:retry 42     # retry one failed-job ID
```

Workers handle `SIGTERM` and `SIGINT` gracefully after the current job. Hard job timeouts require the PHP `pcntl` extension; without it, jobs still run but cannot be interrupted in-process. Worker logs include job duration, attempt count, pending queue depth, and failed-job count.

Use `--max-jobs=1` or another positive limit for smoke tests, CI, or one-shot cron-style processing.

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
