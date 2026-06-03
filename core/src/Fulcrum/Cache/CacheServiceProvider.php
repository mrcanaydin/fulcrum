<?php

declare(strict_types=1);

namespace Fulcrum\Cache;

use Fulcrum\Container\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CacheManager::class, CacheManager::class);
    }
}
