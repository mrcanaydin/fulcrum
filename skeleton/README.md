# Fulcrum Skeleton

Application template for Fulcrum.

## Create A Project

```bash
composer create-project fulcrum/skeleton myapp
cd myapp
cp .env.example .env
php -S 127.0.0.1:8000 -t public
```

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

The Docker stack runs Nginx, PHP-FPM, and MySQL. Nginx listens on `http://127.0.0.1:8080`.

## Structure

- `public/index.php` boots the Fulcrum application.
- `config/*.php` configures app, API middleware, cache, database, events, GraphQL, logging, and storage.
- `database/migrations` stores API database migrations.
- `src/GraphQL/HealthQuery.php` provides the default smoke-test query.
- `docker/nginx/default.conf` is a reference Nginx config for PHP-FPM.

## Migrations

```bash
./vendor/bin/fulcrum make:migration create_users
./vendor/bin/fulcrum migrate
./vendor/bin/fulcrum migrate:status
```

## Input Validation

Use `Fulcrum\Validation\Validator` inside GraphQL resolvers to validate and explicitly sanitize incoming args before touching your domain logic.

## API Middleware

`config/api.php` keeps this template API-only: CORS, request IDs, body size limits, trusted proxy IP handling, JSON `Content-Type` checks, and rate limiting are configured without sessions or UI concerns.

Rate limiting uses the configured cache store from `config/cache.php`; the skeleton defaults to file cache under `storage/cache`.

## Logging

`config/logging.php` defaults to JSON-line file logs at `storage/logs/fulcrum.log`. The global exception handler reports uncaught exceptions before returning API-safe JSON errors.

## Events

`config/events.php` registers synchronous domain event listeners. Use it for API-side hooks such as audit logs, cache invalidation, and post-mutation workflows.
