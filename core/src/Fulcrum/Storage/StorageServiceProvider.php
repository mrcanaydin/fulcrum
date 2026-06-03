<?php

declare(strict_types=1);

namespace Fulcrum\Storage;

use Fulcrum\Container\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(StorageManager::class, StorageManager::class);
    }
}
