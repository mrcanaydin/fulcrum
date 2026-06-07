# Fulcrum GraphQL-Native Roadmap

Each sprint is small enough to implement, review, and release independently.
Complete them in order unless a production issue changes the priority.

## Sprint 1: Authorization Hardening

Status: Complete

Goal: ensure protected GraphQL fields cannot be reached anonymously or with insufficient abilities.

- Make `#[RequiresAbility]` imply authentication.
- Protect administrative mutations in the skeleton.
- Protect generated update and delete mutations by default.
- Test anonymous, insufficient-ability, explicit-ability, and wildcard access.

Done when: protected resolvers reject anonymous and unauthorized callers without invoking the resolver.

## Sprint 2: Typed GraphQL Errors

Status: Complete

Goal: give clients a stable, machine-readable error contract.

- Add errors for `UNAUTHENTICATED`, `FORBIDDEN`, `VALIDATION_FAILED`, and `NOT_FOUND`.
- Preserve safe client errors while masking internal exceptions.
- Include request IDs in error extensions and logs.
- Document the error contract.

Done when: clients never need to parse human-readable error messages.

## Sprint 3: GraphQL Type System

Status: Complete

Goal: support type constructs needed by real application schemas.

- Add input object and enum attributes.
- Add custom scalar registration.
- Ship `Date`, `DateTime`, `JSON`, `Decimal`, and `URL` scalars.
- Add field deprecation support.

Done when: schemas model typed mutation inputs and common API values without string fallbacks.

## Sprint 4: Cursor Pagination

Status: Complete

Goal: provide a consistent, scalable pagination convention.

- Add cursor encode/decode helpers.
- Add connection, edge, and page-info types.
- Add query-builder cursor pagination.
- Update resource scaffolding and examples.

Done when: generated list queries use stable cursor pagination with bounded page sizes.

## Sprint 5: Query Safety

Status: Complete

Goal: protect execution from expensive or abusive GraphQL documents.

- Add configurable depth, complexity, alias-count, and operation-count limits.
- Make introspection configurable by environment.
- Add execution time limits where supported.
- Return typed errors and metrics for rejected operations.

Done when: expensive documents are rejected before resolver execution.

## Sprint 6: Transactions And Mutation Reliability

Status: Complete

Goal: make multi-step mutations and side effects consistent.

- Add database transaction APIs.
- Add after-commit events and queued jobs.
- Add mutation transaction helpers.
- Add idempotency-key support for selected mutations.

Done when: failed mutations cannot leave partial database or side-effect state.

## Sprint 7: Queue Reliability

Status: Complete

Goal: make background processing safe for production workloads.

- Make database job reservation atomic.
- Add failed-job storage, retry backoff, timeouts, and dead-letter handling.
- Add stale reservation recovery and graceful worker shutdown.
- Add worker and queue-depth metrics.

Done when: jobs can be retried, inspected, and recovered without silent loss.

## Sprint 8: Observability And Health

Status: Complete

Goal: make GraphQL behavior diagnosable in production.

- Log operation name, duration, complexity, status, and request ID.
- Add resolver timing hooks and metrics.
- Split liveness and readiness endpoints.
- Check database, cache, queue, and storage readiness.

Done when: operators can identify slow operations and unhealthy dependencies.

## Sprint 9: Persisted Queries And Schema Tooling

Status: Complete

Goal: improve performance, deployment safety, and client integration.

- Add automatic persisted queries and allow-list mode.
- Cache compiled schemas.
- Add schema export and validation CLI commands.
- Add schema-diff checks for breaking changes in CI.

Done when: production can restrict operations and detect breaking schema changes before deployment.

## Sprint 10: Internationalization

Status: Complete

Goal: support backend locale responsibilities without coupling UI translation to the API.

- Resolve locale from explicit input, user preference, `Accept-Language`, then app default.
- Add locale to `RequestContext`.
- Add a translator service for server-generated messages.
- Make mail, notifications, and relevant cache keys locale-aware.
- Keep GraphQL error codes stable and language-independent.

Done when: server-generated content is localized while frontend UI remains frontend-owned.

## Sprint 11: Subscriptions And Uploads

Status: Complete

Goal: support real-time events and large-file workflows with production-friendly transports.

- Implement subscriptions over a documented WebSocket or SSE protocol.
- Add event publication and authorization hooks.
- Add signed upload URL support.
- Document scaling and deployment requirements.

Done when: clients securely subscribe to events and upload files without routing large payloads through GraphQL.

## Sprint 12: Authentication Lifecycle

Status: Complete

Goal: provide a secure credential-to-token lifecycle for API clients.

- Add throttled credential login with generic failures.
- Issue expiring tokens with server-configured abilities.
- Revoke only tokens owned by the authenticated user.
- Reject tokens for banned users and provide current-token logout.
- Document password and token lifecycle responsibilities.

Done when: clients can securely log in and out without choosing their own privileges or revoking another user's tokens.

## Sprint 13: Roles And Permissions

Status: Complete

Goal: support application-defined user groups and permissions without hard-coding domain policy into the framework.

- Make skeleton user passwords mandatory.
- Add reusable roles, permissions, role assignments, and direct permissions.
- Merge role and direct permissions into existing `#[RequiresAbility]` authorization.
- Support wildcard administrator roles.
- Document application-owned permission and management policies.

Done when: applications can define groups such as admin and editor and protect existing GraphQL fields with their permissions.

## Sprint 14: Production Readiness Baseline

Status: Complete

Goal: define and enforce the minimum quality gates for running Fulcrum-backed APIs in production-like environments.

- Add CI integration tests against real PostgreSQL, MySQL, and Redis services.
- Run the skeleton smoke test in CI with Docker Compose.
- Add a production readiness checklist to the core and skeleton docs.
- Document supported infrastructure responsibilities such as connection pooling, TLS termination, process supervision, backups, and secret management.
- Add a release checklist covering migrations, schema export, static analysis, tests, and smoke tests.

Done when: every release can prove the framework boots, migrates, serves health checks, and executes core workflows against real service dependencies.

## Sprint 15: Database Portability And Migration Hardening

Status: In Progress

Goal: make database behavior predictable across PostgreSQL and MySQL without relying on accidental SQL compatibility.

- Add migration integration tests for the skeleton schema on PostgreSQL and MySQL.
- Add database-specific helpers or a small schema builder for common migration operations.
- Audit existing migrations for non-portable SQL and rollback behavior.
- Document supported SQL types, indexes, timestamps, auto-increment identities, and portability limits.
- Add tests for PostgreSQL and MySQL insert IDs, transactions, locks, and queue reservation semantics.

Done when: supported relational databases pass the same migration, rollback, queue, and model behavior tests.

## Sprint 16: Security Review And Hardening

Status: Complete

Goal: reduce the risk of unsafe defaults or framework-level security regressions.

- Review authentication, token storage, ability checks, rate limiting, CORS, uploads, and validation defaults.
- Add regression tests for common authorization bypass and input validation failure modes.
- Document production-safe environment defaults and dangerous debug settings.
- Add guidance for password hashing, token TTLs, trusted proxies, HTTPS-only deployments, and secret rotation.
- Verify GraphQL error masking, request IDs, and audit-relevant logs under production config.

Done when: a production-configured skeleton fails closed for auth, validation, error exposure, proxy trust, and upload safety.

## Sprint 17: Operations And Deployment Guides

Status: Complete

Goal: make Fulcrum applications straightforward to run, observe, and recover in production.

- Add deployment guides for Docker Compose, container platforms, and traditional PHP-FPM/Nginx setups.
- Document queue worker supervision with systemd, Supervisor, or container restart policies.
- Document readiness/liveness probes, log destinations, schema cache warming, and migration ordering.
- Add backup and restore guidance for database-backed queues, tokens, idempotency keys, and subscription events.
- Add notes for PgBouncer compatibility without making pooling a framework responsibility.

Done when: operators can deploy the skeleton with clear steps for web, worker, cache, database, logs, health checks, and rollback.

## Sprint 18: Production Dogfood App

Status: Planned

Goal: validate the framework through a small but real API built on top of Fulcrum.

- Build a minimal production-shaped app using auth, roles, GraphQL mutations, queues, mail/log notifications, uploads, health checks, and persisted queries.
- Run it against PostgreSQL and Redis with production-like config.
- Add load and concurrency checks for login, token-authenticated GraphQL, queue workers, and idempotent mutations.
- Capture framework pain points as issues and fix blockers before declaring beta.
- Publish a short production-readiness report from the dogfood run.

Done when: a real Fulcrum app can run through deploy, migrate, smoke, load, recover, and rollback exercises without manual framework fixes.
