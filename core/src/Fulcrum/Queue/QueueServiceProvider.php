<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Fulcrum\Container\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(JobRunner::class, JobRunner::class);
        $this->container->singleton(QueueManager::class, QueueManager::class);
        $this->container->singleton(QueueWorker::class, QueueWorker::class);
    }
}
