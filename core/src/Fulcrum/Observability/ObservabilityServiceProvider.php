<?php

declare(strict_types=1);

namespace Fulcrum\Observability;

use Fulcrum\Container\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(HealthChecker::class, HealthChecker::class);
    }
}
