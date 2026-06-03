<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Container\ServiceProvider;

/**
 * Registers the DatabaseManager into the container.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(DatabaseManager::class, DatabaseManager::class);
    }

    public function boot(): void
    {
        // Boot actions, e.g., setting up a global DB facade if needed.
    }
}
