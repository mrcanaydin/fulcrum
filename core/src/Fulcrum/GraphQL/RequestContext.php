<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Container\Contracts\ContainerInterface;
use Fulcrum\Routing\Request;

/**
 * Immutable value object passed as the third argument ($context) to every
 * GraphQL resolver.
 *
 * The resolver can retrieve:
 *   - $ctx->user()      → the authenticated model/array, or null for guests
 *   - $ctx->request()   → the raw HTTP Request
 *   - $ctx->container() → the DI container (resolve services on-demand)
 *   - $ctx->isAuth()    → bool convenience check
 */
final class RequestContext
{
    private DataLoaderRegistry $loaderRegistry;

    public function __construct(
        private readonly Request            $request,
        private readonly ContainerInterface $container,
        private readonly mixed              $user = null,
        ?DataLoaderRegistry $loaders = null,
    ) {
        $this->loaderRegistry = $loaders ?? new DataLoaderRegistry();
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function request(): Request
    {
        return $this->request;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /** The authenticated user model/array, or null for unauthenticated requests. */
    public function user(): mixed
    {
        return $this->user;
    }

    /** True when a user was successfully authenticated for this request. */
    public function isAuth(): bool
    {
        return $this->user !== null;
    }

    public function loaders(): DataLoaderRegistry
    {
        return $this->loaderRegistry;
    }

    // ─── Mutation (returns new instance) ─────────────────────────────────────

    /** Produce a new context carrying an authenticated user. */
    public function withUser(mixed $user): self
    {
        return new self($this->request, $this->container, $user, $this->loaders());
    }
}
