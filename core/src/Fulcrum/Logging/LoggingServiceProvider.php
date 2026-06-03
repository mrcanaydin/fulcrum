<?php

declare(strict_types=1);

namespace Fulcrum\Logging;

use Fulcrum\Container\Container;
use Fulcrum\Container\ServiceProvider;
use Psr\Log\LoggerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(LoggerManager::class, LoggerManager::class);
        $this->container->singleton(LoggerInterface::class, function (Container $container): LoggerInterface {
            $manager = $container->make(LoggerManager::class);

            if (!$manager instanceof LoggerManager) {
                throw new \RuntimeException('Logger manager is not registered.');
            }

            return $manager->channel();
        });
    }
}
