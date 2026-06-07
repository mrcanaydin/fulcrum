# Fulcrum Skeleton Operations Guide

This guide turns the skeleton into an operator-facing deployment runbook. It assumes one web process serving `public/index.php`, one or more queue workers running `php fulcrum queue:work`, PostgreSQL or MySQL, and a shared Redis cache for multi-instance deployments.

## Deployment Flow

Use this order for every production deployment:

1. Build the application image or release artifact.
2. Inject production secrets and set `APP_ENV=production` with `APP_DEBUG=false`.
3. Run `php fulcrum schema:validate`.
4. Run `php fulcrum schema:diff path/to/baseline.graphql` when you publish a client-facing schema baseline.
5. Warm the schema snapshot with `php fulcrum schema:cache`.
6. Run `php fulcrum migrate:status`, then `php fulcrum migrate` exactly once for the release.
7. Start or reload web processes.
8. Start or reload queue workers separately from web processes.
9. Verify `GET /health/live`, `GET /health/ready`, and one GraphQL request.

Rollback order:

1. Stop accepting new traffic or roll traffic back at the load balancer.
2. Restore the previous application image or release artifact.
3. Run `php fulcrum migrate:rollback` only when the release migration set is known to be reversible and the old code is compatible with the rolled-back schema.
4. Restart workers after the code and schema are aligned again.

## Docker Compose

The included `docker-compose.yml` is the reference single-host deployment shape:

- `nginx` serves HTTP and forwards PHP requests to `php`.
- `php` runs the Fulcrum web application.
- `redis` backs rate limiting, persisted queries, and shared cache state.
- `postgres` stores application data, jobs, idempotency keys, tokens, and subscription events.

For a production-like Compose deployment:

1. Replace local bind mounts with immutable images or read-only application mounts.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, exact `CORS_ALLOWED_ORIGINS`, and correct `TRUSTED_PROXIES`.
3. Run migrations as a one-shot task before scaling web or worker services.
4. Add a separate worker service:

```yaml
worker:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  working_dir: /var/www/html
  command: ["php", "fulcrum", "queue:work", "--sleep=3"]
  restart: unless-stopped
  environment:
    APP_ENV: production
    APP_DEBUG: "false"
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_PORT: 5432
    DB_DATABASE: fulcrum
    DB_USERNAME: fulcrum
    DB_PASSWORD: ${DB_PASSWORD}
    CACHE_STORE: redis
    REDIS_HOST: redis
    REDIS_PORT: 6379
    QUEUE_CONNECTION: database
  depends_on:
    postgres:
      condition: service_healthy
    redis:
      condition: service_started
```

5. Keep logs on stdout/stderr or ship `storage/logs/*.log` to a collector.
6. Reuse the included `scripts/smoke.sh` steps as a post-deploy smoke test.

## Container Platforms

For Kubernetes, Nomad, ECS, or similar platforms, split the release into distinct workloads:

- Web workload:
  Runs Nginx plus PHP-FPM, or a PHP application container behind the platform ingress.
- Worker workload:
  Runs `php fulcrum queue:work --sleep=3`.
- Migration job:
  Runs `php fulcrum migrate` once per release.

Recommended container commands:

```bash
php fulcrum schema:validate
php fulcrum schema:cache
php fulcrum queue:work --sleep=3
```

Recommended probe mapping:

- Liveness: `GET /health/live`
- Readiness: `GET /health/ready`

Do not route public traffic to a pod until readiness is healthy. Restart web pods independently from workers so long-running queue jobs do not share web lifecycle rules.

## Traditional PHP-FPM And Nginx

For VM or bare-metal deployments:

1. Install PHP with the required PDO extensions for your database and any optional extensions your app uses.
2. Point Nginx `root` to `public/`.
3. Forward PHP requests to PHP-FPM.
4. Disable buffering for `/graphql/stream` when subscriptions are enabled.
5. Run queue workers under a supervisor rather than inside PHP-FPM.

The included [default.conf](/mnt/Depo/fulcrum/skeleton/docker/nginx/default.conf:1) is the reference Nginx shape. A minimal PHP-FPM/Nginx deployment still needs:

- Shared Redis when you run more than one web instance.
- One migration step per release.
- A separate queue worker process.
- Log shipping for both Nginx and the Fulcrum application log.

## Queue Worker Supervision

Queue workers should restart automatically and stop gracefully during deploys.

Example `systemd` unit:

```ini
[Unit]
Description=Fulcrum queue worker
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/fulcrum
ExecStart=/usr/bin/php fulcrum queue:work --sleep=3
Restart=always
RestartSec=5
User=www-data
Group=www-data
KillSignal=SIGTERM

[Install]
WantedBy=multi-user.target
```

Example Supervisor program:

```ini
[program:fulcrum-worker]
command=/usr/bin/php /var/www/fulcrum/fulcrum queue:work --sleep=3
directory=/var/www/fulcrum
autostart=true
autorestart=true
stopsignal=TERM
user=www-data
stdout_logfile=/var/log/fulcrum/worker.log
stderr_logfile=/var/log/fulcrum/worker-error.log
```

Container platforms should use the platform restart policy for the worker workload instead of embedding another supervisor.

## Probes, Logs, Schema Cache, And Migrations

Health endpoints:

- `GET /health/live` checks only that the process can answer HTTP.
- `GET /health/ready` checks database, cache, queue, and storage integrations.

Log destinations:

- Nginx or ingress logs should go to your platform log sink.
- Fulcrum application logs default to `storage/logs/fulcrum.log`.
- Mail and notification development logs default to `storage/logs/mail.log` and `storage/logs/notifications.log`.

Schema cache and deployment checks:

```bash
php fulcrum schema:validate
php fulcrum schema:cache
php fulcrum schema:export storage/schema.graphql
php fulcrum schema:diff path/to/baseline.graphql
```

Migration ordering:

1. Validate and cache schema.
2. Run database migrations once.
3. Start or reload web.
4. Start or reload workers.
5. Run health and GraphQL smoke checks.

Avoid letting every web instance race to run `php fulcrum migrate`.

## Backup And Restore

Back up the relational database on a schedule that matches your recovery goals. Fulcrum keeps several operational data sets in the database:

- `jobs` for queued work that has not run yet
- `failed_jobs` for retry and incident review
- `personal_access_tokens` for active API sessions
- `idempotency_keys` for replay protection on selected mutations
- `subscription_events` for resumable SSE delivery

Backup guidance:

- Take regular database backups and test restores, not just backup creation.
- Keep retention long enough to investigate failed jobs and auth incidents.
- Document whether queued jobs should be replayed after restore or intentionally dropped.

Restore guidance:

1. Restore the database backup.
2. Reconcile whether `jobs` should be replayed, pruned, or re-enqueued from domain state.
3. Consider revoking restored personal access tokens if the restore crosses an incident window.
4. Clear idempotency keys only when you intentionally allow the same client mutations to run again.
5. Prune stale `subscription_events` after restore if clients should reconnect from fresh state instead of replaying old streams.

## PgBouncer And Connection Pooling

PgBouncer is optional infrastructure, not a framework dependency. Fulcrum should stay compatible with transaction pooling when applications avoid session-scoped PostgreSQL features.

Recommended PgBouncer posture:

- Prefer transaction pooling.
- Keep migrations and admin tasks on direct database connections if your operational tooling needs session-level features.
- Verify queue workers, readiness checks, and migrations against the same pooling mode before rollout.

## Recovery Checklist

Use this short checklist during incidents or deploy validation:

1. `GET /health/live` returns `200`.
2. `GET /health/ready` returns `200`.
3. One GraphQL query succeeds through the public entrypoint.
4. `php fulcrum queue:work --max-jobs=1 --sleep=0` can claim and complete a job.
5. Logs contain request IDs for the tested request path.
