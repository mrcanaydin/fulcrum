<?php

declare(strict_types=1);

namespace Fulcrum\Container;

use Fulcrum\Container\Contracts\ContainerInterface;
use Fulcrum\Container\Exceptions\ContainerException;
use Fulcrum\Container\Exceptions\NotFoundException;

/**
 * PSR-11 compliant DI Container with auto-wiring support.
 *
 * Binding types:
 *   bind()      → transient: new instance on every resolution
 *   singleton() → shared:    resolved once, then cached
 *   instance()  → pre-built: registered value returned as-is
 *
 * If no binding exists, the container will attempt to auto-wire
 * the class via Reflection (see AutoWirer).
 */
class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: callable, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> Alias → Abstract */
    private array $aliases = [];

    private AutoWirer $autoWirer;

    public function __construct()
    {
        $this->autoWirer = new AutoWirer($this);

        // Bind the container to itself so it can be injected
        $this->instance(static::class, $this);
        $this->instance(ContainerInterface::class, $this);
        $this->instance(\Psr\Container\ContainerInterface::class, $this);
    }

    // ─── Binding ────────────────────────────────────────────────────────────

    public function bind(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $this->normalise($concrete),
            'shared'   => false,
        ];
    }

    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $this->normalise($concrete),
            'shared'   => true,
        ];
    }

    public function instance(string $abstract, mixed $value): void
    {
        $this->instances[$abstract] = $value;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    // ─── Resolution ─────────────────────────────────────────────────────────

    /**
     * Resolve the given abstract from the container.
     *
     * @param  array<string, mixed> $parameters  Explicit constructor overrides
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->resolveAlias($abstract);

        // Return a cached instance (singleton or pre-bound)
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Use an explicit binding
        if (isset($this->bindings[$abstract])) {
            $binding  = $this->bindings[$abstract];
            $resolved = ($binding['concrete'])($this, $parameters);

            if ($binding['shared']) {
                $this->instances[$abstract] = $resolved;
            }

            return $resolved;
        }

        // Fall back to auto-wiring
        return $this->autoWirer->make($abstract, $parameters);
    }

    // ─── PSR-11 ─────────────────────────────────────────────────────────────

    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (ContainerException $e) {
            throw $e; // already PSR-11 compliant
        } catch (\Throwable $e) {
            throw new NotFoundException(
                "No entry found for [{$id}]: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    // ─── Introspection ──────────────────────────────────────────────────────

    public function bound(string $abstract): bool
    {
        $abstract = $this->resolveAlias($abstract);

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /** @return array<string, array{concrete: callable, shared: bool}> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function resolveAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Wrap a string class name into a callable so all bindings are uniformly callable.
     *
     * We call autoWirer->make() directly (not container->make()) to avoid
     * infinite recursion when $abstract === $concrete. The autoWirer's own
     * dependency resolution still goes through container->make(), so nested
     * bindings are fully respected.
     */
    private function normalise(string|callable $concrete): callable
    {
        if (is_callable($concrete)) {
            return $concrete;
        }

        return fn (Container $_container, array $params) => $this->autoWirer->make($concrete, $params);
    }
}
