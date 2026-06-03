<?php

declare(strict_types=1);

namespace Fulcrum\Container;

/**
 * Base class for all Fulcrum service providers.
 *
 * Service providers are the central place to configure and bootstrap
 * framework modules. Every provider is instantiated with the application
 * container, giving it full access to bind, singleton, and resolve services.
 *
 * Lifecycle:
 *   1. register()  — bind services into the container (no inter-provider dependencies yet)
 *   2. boot()      — run after ALL providers have registered (safe to resolve other services)
 */
abstract class ServiceProvider
{
    public function __construct(protected readonly Container $container) {}

    /**
     * Register bindings into the container.
     * Do NOT resolve other services here — they may not be registered yet.
     */
    abstract public function register(): void;

    /**
     * Perform any post-registration bootstrapping.
     * At this point every service provider has already called register().
     */
    public function boot(): void {}

    // ─── Convenience proxies ────────────────────────────────────────────────

    protected function bind(string $abstract, string|callable $concrete): void
    {
        $this->container->bind($abstract, $concrete);
    }

    protected function singleton(string $abstract, string|callable $concrete): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    protected function instance(string $abstract, mixed $value): void
    {
        $this->container->instance($abstract, $value);
    }

    protected function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }
}
