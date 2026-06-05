# Fulcrum Skeleton

Application template for Fulcrum.

## Create A Project

```bash
composer create-project fulcrum/skeleton myapp
cd myapp
cp .env.example .env
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000/` in a browser preview to see JSON API metadata. This template is headless, so there is no HTML UI. GraphQL operations are sent with `POST /graphql`.

Smoke query:

```bash
curl -X POST http://127.0.0.1:8000/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ health }"}'
```

Expected response:

```json
{"data":{"health":"ok"}}
```

## Docker

```bash
./scripts/smoke.sh
```

The Docker stack runs Nginx, PHP-FPM, Redis, and MySQL. Nginx listens on `http://127.0.0.1:8080`.

## Structure

- `public/index.php` boots the Fulcrum application.
- `config/*.php` configures app, API middleware, cache, database, events, GraphQL, logging, and storage.
- `database/migrations` stores API database migrations.
- `database/seeders` stores demo-data seeders.
- `database/factories` stores test/demo data factories.
- `src/GraphQL/HealthQuery.php` provides the default smoke-test query.
- `docker/nginx/default.conf` is a reference Nginx config for PHP-FPM.

## Migrations

```bash
php fulcrum make:migration create_users
php fulcrum migrate
php fulcrum migrate:status
```

The skeleton includes an example `users` migration at `database/migrations/2026_01_01_000000_create_users_table.php`.

## Demo Data

```bash
php fulcrum make:seeder UserSeeder
php fulcrum make:factory UserFactory
php fulcrum db:seed
```

The skeleton includes `database/seeders/DatabaseSeeder.php` and `database/factories/UserFactory.php` as generic examples for seeding API demo data.

## Example API

The template ships with a tiny user example built on Fulcrum's model layer:

- `src/Models/User.php` extends `Fulcrum\Database\Model`.
- `src/GraphQL/UserType.php` defines the `User` GraphQL object type.
- `src/GraphQL/UserQuery.php` exposes `user(id:)` and cursor-paginated `users(first:, after:)`.
- `src/GraphQL/UserMutation.php` exposes `createUser`, email verification, and ban/unban mutations with validation and sanitization.

Cursor pagination query:

```graphql
query {
  users(first: 20, after: null) {
    nodes { id name email }
    edges { cursor node { id name } }
    pageInfo { hasNextPage endCursor }
  }
}
```

```bash
php fulcrum migrate

curl -X POST http://127.0.0.1:8000/graphql \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: create-ada-001' \
  -d '{"query":"mutation { createUser(name: \"Ada Lovelace\", email: \"ADA@EXAMPLE.COM\") { id name email } }"}'
```

Optional user features are configured in `config/users.php`. Email verification is disabled by default:

```env
USER_EMAIL_VERIFICATION_ENABLED=false
USER_EMAIL_VERIFICATION_EXPIRES_MINUTES=60
```

When enabled, `createUser` and `sendUserEmailVerification(email:)` queue `App\Jobs\SendEmailVerificationJob`, which sends through Fulcrum's mail manager.

User fields include `avatar`, `gender`, `birthday`, `email_verified_at`, `banned_at`, and `ban_reason`. Ban management is available through `banUser(id:, reason:)` and `unbanUser(id:)`.

## Input Validation

Use `Fulcrum\Validation\Validator` inside GraphQL resolvers to validate and explicitly sanitize incoming args before touching your domain logic.

## CRUD Scaffolding

```bash
php fulcrum make:model Post
php fulcrum make:resource Post title:string published:boolean
```

`make:resource` generates a model, GraphQL type, edge, connection, query, and mutation. Add the generated GraphQL classes to `config/graphql.php`.

Use eager loading when returning nested GraphQL data:

```php
User::query()->with('posts')->cursorPaginate(first: 20)->toArray();
```

## API Middleware

`config/api.php` keeps this template API-only: CORS, request IDs, body size limits, trusted proxy IP handling, JSON `Content-Type` checks, and rate limiting are configured without sessions or UI concerns.

Rate limiting uses the configured cache store from `config/cache.php`. Local `.env` defaults to file cache, while Docker uses Redis so rate limits work across PHP-FPM workers and future multi-instance deployments.

## GraphQL Query Safety

`config/graphql.php` bounds query depth, complexity, aliases, operation count, execution duration, and introspection. Production defaults disable introspection and allow one operation per request. Environment variables such as `GRAPHQL_MAX_DEPTH`, `GRAPHQL_MAX_COMPLEXITY`, and `GRAPHQL_INTROSPECTION` can override these settings.

## Persisted Queries And Schema CI

Automatic persisted queries are enabled by default. Clients send `extensions.persistedQuery.version = 1` and the query SHA-256 hash; after registration they may omit the full query. Enable `GRAPHQL_ALLOW_LIST=true` in production to accept only operations deployed in `graphql-allow-list.json`.

```bash
php fulcrum schema:validate
php fulcrum schema:export storage/schema.graphql
php fulcrum schema:diff baseline/schema.graphql
```

`schema:diff` exits with failure when it detects breaking changes. Canonical schema SDL and its fingerprint are cached automatically through the configured cache store.

## Reliable Mutations

The example `createUser` mutation is idempotent and transactional. Run migrations to create the included `idempotency_keys` table, then send a unique `Idempotency-Key` header. Retrying the same mutation with the same key and arguments returns its original result without creating another user.

Events and verification jobs from `createUser` use after-commit dispatch, so they are discarded when the user write rolls back.

## Logging

`config/logging.php` defaults to JSON-line file logs at `storage/logs/fulcrum.log`. The global exception handler reports uncaught exceptions before returning API-safe JSON errors.

GraphQL operation logs include operation name, request ID, duration, complexity, and status. Resolver logs include individual resolver duration and status; `GRAPHQL_SLOW_RESOLVER_MS` controls when slow resolver records become warnings.

## Health Checks

`GET /health/live` is a dependency-free liveness probe. `GET /health/ready` and its `/health` alias perform real database, cache, queue, and storage checks and return HTTP `503` if any enabled dependency fails.

Use `HEALTH_CHECK_DATABASE`, `HEALTH_CHECK_CACHE`, `HEALTH_CHECK_QUEUE`, and `HEALTH_CHECK_STORAGE` to enable or disable individual readiness probes. The smoke script requires both liveness and readiness to pass.

## Mail

`config/mail.php` defaults to the `log` mailer for development. Sent mail is written to `storage/logs/mail.log`, which makes verification emails and notification jobs easy to inspect without an SMTP account.

```env
MAIL_MAILER=log
MAIL_LOG_PATH=/var/www/html/storage/logs/mail.log
```

For production, switch to SMTP:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-user
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
```

## Notifications & Hooks

`config/notifications.php` defines queued push-style notifications and queued email hooks for domain events. This lets API developers choose exactly which actions trigger messages without putting provider code inside GraphQL resolvers.

The skeleton includes disabled examples for `App\Events\UserCreated`:

```env
USER_WELCOME_EMAIL_ENABLED=true
USER_WELCOME_PUSH_ENABLED=true
```

Notification channels:

- `log` writes JSON-line push payloads to `storage/logs/notifications.log` for development.
- `webhook` posts JSON payloads to `NOTIFICATION_WEBHOOK_URL`, useful for FCM/APNs gateway services.

Hook placeholders use public event properties, for example `{userId}` and `{email}` from `App\Events\UserCreated`.

## Commands, Scheduler & Queues

Register app commands in `config/console.php`, scheduled tasks in `config/schedule.php`, and queue settings in `config/queue.php`.

```bash
php fulcrum api-data:fetch --sort=new
php fulcrum queue:work
php fulcrum schedule:run
```

The example `App\Console\FetchApiDataCommand` dispatches `App\Jobs\FetchApiDataJob`. Local `.env` defaults to the `sync` queue driver; Docker uses the `database` driver and the included `jobs` migration.

The database queue includes atomic reservation, stale-job recovery, exponential retry backoff, worker timeouts, and a `failed_jobs` dead-letter table. Configure these through `QUEUE_RETRY_AFTER`, `QUEUE_TRIES`, `QUEUE_TIMEOUT`, `QUEUE_BACKOFF`, and `QUEUE_MAX_BACKOFF`.

```bash
php fulcrum queue:work
php fulcrum queue:status
php fulcrum queue:failed
php fulcrum queue:retry
```

`queue:work` keeps listening for new jobs and stops gracefully on `SIGTERM` or `SIGINT`. Use `--max-jobs=1` for smoke tests or one-shot processing.

## Events

`config/events.php` registers synchronous domain event listeners. Use it for API-side hooks such as audit logs, cache invalidation, and post-mutation workflows.

The example `createUser` mutation dispatches `App\Events\UserCreated`, which is logged by `App\Listeners\LogUserCreated`.
