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
