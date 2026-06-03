<?php

declare(strict_types=1);

namespace Fulcrum\Container\Contracts;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a transient binding — a new instance is created on every resolution.
     */
    public function bind(string $abstract, string|callable $concrete): void;

    /**
     * Register a shared binding — resolved once and cached for subsequent calls.
     */
    public function singleton(string $abstract, string|callable $concrete): void;

    /**
     * Register an already-resolved value (pre-built instance or scalar).
     */
    public function instance(string $abstract, mixed $value): void;

    /**
     * Register an alias so that resolving $alias delegates to $abstract.
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Resolve the given abstract type, optionally injecting extra parameters.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Determine if the abstract is explicitly bound (not just auto-wireable).
     */
    public function bound(string $abstract): bool;
}
